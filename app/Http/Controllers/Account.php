<?php

namespace App\Http\Controllers;

use App\Models\LiveStreamCompanies as mLiveStreamCompanies;
use App\Models\LiveStreamCompanyUsers as mLiveStreamCompanyUsers;
use App\Models\PasswordResets as mPasswordResets;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Jobs\SendMailResetPassword;

class Account extends API
{
    public function doCreate(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'name' => ['required', 'string', 'min:4', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
            'phone_country' => ['required', 'string', 'size:2'],
            'phone_country_dial' => ['nullable', 'string', 'min:2', 'max:5'],
            'phone' => ['required', 'string', 'min:6', 'max:20'],
            'brand_name' => ['nullable', 'string', 'min:6', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'password_confirmation' => ['nullable', 'string', 'min:6', 'max:100'],
            'type' => ['nullable', 'integer'],
            'surname' => ['nullable', 'string', 'min:4', 'max:40'],
            'terms' => ['nullable', new strBoolean],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $params['name'] = isset($params['name']) ? trim($params['name']) : null;
        $params['surname'] = isset($params['surname']) ? trim($params['surname']) : null;
        $params['email'] = isset($params['email']) ? strtolower(trim($params['email'])) : null;
        $params['type'] = isset($params['type']) ? trim($params['type']) : null;
        $params['phone_country'] = isset($params['phone_country']) ? trim($params['phone_country']) : null;
        $params['phone_country_dial'] = isset($params['phone_country_dial']) ? trim($params['phone_country_dial']) : null;
        $params['phone'] = isset($params['phone']) ? trim(preg_replace('/[^0-9]/', '', $params['phone'])) : null;
        $params['brand_name'] = isset($params['brand_name']) ? trim($params['brand_name']) : null;
        $params['password'] = isset($params['password']) ? trim($params['password']) : null;
        $params['password_confirmation'] = isset($params['password_confirmation']) ? trim($params['password_confirmation']) : null;

        if ($params['phone'] !== null) {
            if (strlen($params['phone']) < 6) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone number must be at least 6 characters long.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if ($params['phone_country'] === null) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone country code is required.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if (!preg_match('/^[A-Za-z]+$/', $params['phone_country'])) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone country code must be only letters.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            } else {
                $params['phone_country'] = strtoupper($params['phone_country']);
            }

            if ($params['phone_country_dial'] === null) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone country dial code is required.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if (!preg_match('/^\+[0-9]+$/', $params['phone_country_dial'])) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone country dial code must be only numbers with a plus sign.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        }

        if ($params['password_confirmation'] !== null && $params['password'] !== $params['password_confirmation']) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Password confirmation does not match.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if (isset($params['terms'])) {
            $params['terms'] = filter_var($params['terms'], FILTER_VALIDATE_BOOLEAN);
            if (!$params['terms']) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('You must agree to the terms and conditions.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        }

        if (mLiveStreamCompanyUsers::where('email', '=', $params['email'])->orWhere('phone', '=', $params['phone'])->exists()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Email or phone number already taken.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if($params['type'] == 2){

        if (mLiveStreamCompanies::where('name', '=', $params['brand_name'])->exists()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Brand name already taken.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

    }

        $company_id = Str::uuid()->toString();

        try {
            $company = new mLiveStreamCompanies;
            $company->id = $company_id;
            $company->name = $params['brand_name'];
            $company->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to create company.'),
            ];
            if (config('app.debug')) {
                $message->debug = (object) [
                    'message' => $e->getMessage(),
                ];
            }
            $r->messages[] = (object) $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $user_id = Str::uuid()->toString();

        try {
            $company_user = new mLiveStreamCompanyUsers;
           // dd($company_user);
            $company_user->id = $user_id;
            $company_user->role = 1;
            $company_user->company_id = $company_id;
            $company_user->email = $params['email'];
            $company_user->type =  $params['type'];
            $company_user->name = $params['name'];
            $company_user->surname = $params['surname'];
            $company_user->password = Hash::make($params['password']);
            $company_user->phone_country = $params['phone_country'];
            $company_user->phone_country_dial = $params['phone_country_dial'];
            $company_user->phone = $params['phone'];
            $company_user->is_master = true;
            $company_user->save();
        } catch (\Exception $e) {
            try {
                $company->delete();
            } catch (\Exception $e) {
                //
            }

            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to create user account.'),
            ];
            if (config('app.debug')) {
                $message->debug = (object) [
                    'message' => $e->getMessage(),
                ];
            }
            $r->messages[] = (object) $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $check_interval = 300; // seconds
        $timeout = 3; // seconds
        $start = microtime(true);

        // Wait for the company user to be created (This method is not ideal, but work with database replication)
        while (true) {
            $company_user = mLiveStreamCompanyUsers::find($user_id);
            if ($company_user !== null) {
                break;
            }
            if (microtime(true) - $start >= $timeout) {
                try {
                    $company->delete();
                } catch (\Exception $e) {
                    //
                }

                $message = (object) [
                    'type' => 'error',
                    'message' => __('Failed to create user account.'),
                ];
                if (config('app.debug')) {
                    $message->debug = __('Company user could not be found after :timeout seconds', ['timeout' => $timeout]);
                }
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            usleep($check_interval * 1000);
        }

        $token_expires_at = now()->addMinutes(config('session.lifetime'));

        if (($token = $company_user->generateToken($token_expires_at)) === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Failed to create user token.'),
            ];
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Account created successfully.'),
        ];

        $r->success = true;
        $r->data = (object) [
            'login_token' => $token,
            'token_expires_at' => $token_expires_at->toDateTimeString(),
            'company_id' => $company_id,
            'company_user_id' => $user_id,
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function doLogin(Request $request): JsonResponse
    {
        $token_expires_at = now()->addMinutes(config('session.lifetime'));

        // if (config('app.env') === 'local') {
        //     $r = API::INIT();
        //     $r->success = true;
        //     $r->data = (object) [
        //         'token' => mLiveStreamCompanyUsers::where('email', '=', 'kleber.santos@gobliver.com')->first()->generateToken($token_expires_at)
        //     ];
        //     return response()->json($r, Response::HTTP_OK);
        // }

        if (($params = API::doValidate($r, [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:4', 'max:255'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($company_user = API::getCompanyUserByEmail($params['email'], false, $r)) instanceof JsonResponse) {
            return $company_user;
        }

        if ($company_user === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Invalid email address or password.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        // if (!$company_user->hasVerifiedEmail()) {
        //     $r->messages[] = (object) [
        //         'type' => 'error',
        //         'message' => __('Email address is not verified.'),
        //     ];
        //     return response()->json($r, Response::HTTP_UNAUTHORIZED);
        // }

        if (!Hash::check($params['password'], $company_user->password)) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Invalid email address or password.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        $r->success = true;
        $r->data = (object) [
            'token' => $company_user->generateToken($token_expires_at)
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function doLogout(Request $request, ?string $token = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60'],
        ], $request->all(), ['token' => $token])) instanceof JsonResponse) {
            return $params;
        }

        if (($token = API::getToken($params['token'], $r)) instanceof JsonResponse) {
            return $token;
        }

        try {
            $token->delete();
        } catch (\Exception $e) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Failed to logout.'),
            ];
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Logged out successfully.'),
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doValidateToken(Request $request, ?string $token = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60'],
        ], $request->all(), ['token' => $token])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyByToken($params['token'], $r)) instanceof JsonResponse) {
            return $company;
        }

        if (($company_user = API::getCompanyUserByToken($params['token'], $r)) instanceof JsonResponse) {
            return $company_user;
        }

        try {
            $company_user->last_login = now()->format('Y-m-d H:i:s.u');
            $company_user->last_login_ip = $request->ip();
            $company_user->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to update user last login.'),
            ];
            if (config('app.env') === 'local') {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        $r->data = (object) [
            'id' => $company->id,
            'avatar' => $company_user->getAvatar(),
            'created_at' => $company_user->created_at,
            'email' => $company_user->email,
            'name' => $company_user->name,
            'role' => $company_user->role,
            'brand' => (object) [
                'avatar' => $company->getAvatar(),
                'logo' => $company->getLogo(),
                'name' => $company->name,
            ],
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function doResetPassword(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'email' => ['required', 'email', 'max:255'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($company_user = API::getCompanyUserByEmail($params['email'], false, $r)) instanceof JsonResponse) {
            return $company_user;
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('If the email address is valid, you will receive an email with a link to reset your password.'),
        ];

        $r->success = true;

        if ($company_user === null) {
            return response()->json($r, Response::HTTP_OK);
        }

        $token = Str::random(40);

        try {
            $reset = new mPasswordResets();
            $reset->email = $company_user->email;
            $reset->token = $token;
            $reset->shorten_code = Str::random(8);
            $reset->created_at = now()->format('Y-m-d H:i:s.u');
            $reset->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to reset password.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        SendMailResetPassword::dispatch($company_user, $token);
        return response()->json($r, Response::HTTP_OK);
    }

    public function doResetPasswordVerify(Request $request, ?string $token = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:40'],
            'password' => ['required', 'string', 'min:4', 'max:100'],
            'password_confirmation' => ['required', 'string', 'min:4', 'max:100', 'same:password'],
        ], $request->all(), ['token' => $token])) instanceof JsonResponse) {
            return $params;
        }

        if (($reset = mPasswordResets::where('token', $params['token'])->first()) === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Invalid token.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        if ($reset->created_at < now()->subMinutes(30)->timestamp) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Token has expired.'),
            ];
            try {
                $reset->delete();
            } catch (\Exception $e) {
                // Ignore
            }
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        if (($company_user = API::getCompanyUserByEmail($reset->email, false, $r)) instanceof JsonResponse) {
            return $company_user;
        }

        if ($company_user === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Invalid token.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        if (Hash::check($params['password'], $company_user->password)) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('New password cannot be the same as the old password.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        try {
            $company_user->password = Hash::make($params['password']);
            $company_user->save();
        } catch (\Exception $e) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Failed to reset password.'),
            ];
            if (config('app.debug')) {
                $r->messages[0]->debug = $e->getMessage();
            }
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $reset->delete();
        } catch (\Exception $e) {
            // Ignore
        }

        $token_expires_at = now()->addMinutes(config('session.lifetime'));

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Password has been reset.'),
        ];
        $r->success = true;
        $r->data = (object) [
            'login_token' => $company_user->generateToken($token_expires_at)
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function doCreateUser(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60'],
            'email' => ['required', 'string', 'email', 'max:100'],
            'name' => ['required', 'string', 'max:40'],
            'password_confirmation' => ['nullable', 'string', 'min:6', 'max:100', 'same:password'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'phone_country_dial' => ['nullable', 'string', 'min:2', 'max:5'],
            'phone' => ['nullable', 'string', 'min:6', 'max:20'],
            'role' => ['nullable', 'numeric', 'in:1,0'],
            'avatar' => ['nullable', 'uuid', 'size:36', 'exists:medias,id'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyByToken($params['token'], $r)) instanceof JsonResponse) {
            return $company;
        }

        $params['name'] = isset($params['name']) ? strtolower(trim($params['name'])) : null;
        $params['email'] = isset($params['email']) ? strtolower(trim($params['email'])) : null;
        $params['phone_country'] = isset($params['phone_country']) ? trim($params['phone_country']) : null;
        $params['phone_country_dial'] = isset($params['phone_country_dial']) ? trim($params['phone_country_dial']) : null;
        $params['phone'] = isset($params['phone']) ? trim(preg_replace('/[^0-9]/', '', $params['phone'])) : null;
        $params['password'] = isset($params['password']) ? trim($params['password']) : null;
        $params['password_confirmation'] = isset($params['password_confirmation']) ? trim($params['password_confirmation']) : null;
        $params['role'] = isset($params['role']) ? trim($params['role']) : 0;
        $params['avatar'] = isset($params['avatar']) ? trim($params['avatar']) : null;

        if (($company_user = API::getCompanyUserByEmail($params['email'], false, $r)) instanceof JsonResponse) {
            return $company_user;
        }

        if ($company_user !== null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Email address already exists.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        if ($params['password_confirmation'] !== null) {
            if ($params['password'] !== $params['password_confirmation']) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Password confirmation does not match.'),
                ];
                return response()->json($r, Response::HTTP_UNAUTHORIZED);
            }
        }

        if ($params['phone'] !== null) {
            if (strlen($params['phone']) < 6) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone number must be at least 6 characters long.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if (($company_user = API::getCompanyUserByPhone($params['phone'], false, $r)) instanceof JsonResponse) {
                return $company_user;
            }

            if ($company_user !== null) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone number already exists.'),
                ];
                return response()->json($r, Response::HTTP_UNAUTHORIZED);
            }

            if ($params['phone_country'] === null) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone country code is required.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if (!preg_match('/^[A-Za-z]+$/', $params['phone_country'])) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone country code must be only letters.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            } else {
                $params['phone_country'] = strtoupper($params['phone_country']);
            }

            if ($params['phone_country_dial'] === null) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone country dial code is required.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if (!preg_match('/^\+[0-9]+$/', $params['phone_country_dial'])) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Phone country dial code must be only numbers with a plus sign.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        }

        $user_id = Str::uuid()->toString();

        try {
            $company_user = new mLiveStreamCompanyUsers();
            $company_user->id = $user_id;
            $company_user->company_id = $company->id;
            $company_user->email = $params['email'];
            $company_user->password = Hash::make($params['password']);
            $company_user->name = $params['name'];
            $company_user->phone = $params['phone'];
            $company_user->phone_country = $params['phone_country'];
            $company_user->phone_country_dial = $params['phone_country_dial'];
            $company_user->role = $params['role'];
            $company_user->avatar = $params['avatar'];
            $company_user->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to create user.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('User has been created.'),
        ];
        $r->success = true;
        $r->data = (object) [
            'id' => $user_id,
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function getUsers(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:80'],
            'order_by' => ['nullable', 'string', 'in:id,email,created_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyByToken($params['token'], $r)) instanceof JsonResponse) {
            return $company;
        }

        $params['offset'] = $params['offset'] ?? 0;
        $params['limit'] = $params['limit'] ?? 80;
        $params['order_by'] = $params['order_by'] ?? 'id';
        $params['order'] = $params['order'] ?? 'asc';

        $total = Cache::remember('company_users_total_' . $company->id, API::CACHE_TTL, function () use ($company) {
            return $company->users()->count();
        });

        $count = 0;

        $r->success = true;
        $r->data = $company->users()
            ->where('deleted_at', '=', null)
            ->offset($params['offset'])
            ->limit($params['limit'])
            ->orderBy($params['order_by'], $params['order'])
            ->get()
            ->map(function ($user) use (&$count) {
                $user->makeVisible(['role', 'is_master', 'phone', 'phone_country', 'phone_country_dial', 'created_at', 'address', 'city', 'state', 'zip', 'country']);
                $count++;
                return (object) [
                    'id' => $user->id,
                    'created_at' => $user->created_at,
                    'email_verified' => $user->hasVerifiedEmail(),
                    'email' => $user->email,
                    'is_master' => $user->is_master,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'phone_country' => (object) [
                        'code' => $user->phone_country,
                        'dial' => $user->phone_country_dial,
                    ],
                    'role' => $user->role,
                    'avatar' => $user->getAvatar(),
                    'address' => $user->address,
                    'city' => $user->city,
                    'state' => $user->state,
                    'zip' => $user->zip,
                    'country' => $user->country,
                ];
            });

        $r->data_info = (object) [
            'offset' => $params['offset'],
            'limit' => $params['limit'],
            'count' => $count,
            'total' => $total,
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function doUpdateUser(Request $request, ?string $user_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60'],
            'user_id' => ['required', 'string', 'uuid', 'size:36'],
            'name' => ['nullable', 'string', 'min:1', 'max:40'],
            'email' => ['nullable', 'string', 'email', 'min:1', 'max:150'],
            'phone' => ['nullable', 'string', 'min:1', 'max:32'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'phone_country_dial' => ['nullable', 'string', 'min:1', 'max:5'],
            'country' => ['nullable', 'string', 'min:1', 'max:40'],
            'state' => ['nullable', 'string', 'min:1', 'max:40'],
            'city' => ['nullable', 'string', 'min:1', 'max:40'],
            'address' => ['nullable', 'string', 'min:1', 'max:80'],
            'zip' => ['nullable', 'string', 'min:1', 'max:10'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
            'password_confirmation' => ['nullable', 'string', 'min:4', 'max:255', 'same:password'],
            'role' => ['nullable', 'integer', 'min:0', 'max:9'],
            'avatar' => ['nullable', 'string', 'uuid', 'size:36'],
        ], $request->all(), ['user_id' => $user_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyByToken($params['token'], $r)) instanceof JsonResponse) {
            return $company;
        }

        if (($company_user = API::getCompanyUserById($params['user_id'], $r)) instanceof JsonResponse) {
            return $company_user;
        }

        if ($company_user->company_id !== $company->id) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('User does not belong to this company.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        $params['name'] = $params['name'] ?? null;
        $params['email'] = $params['email'] ?? null;
        $params['phone'] = $params['phone'] ?? null;
        $params['phone_country'] = $params['phone_country'] ?? null;
        $params['phone_country_dial'] = $params['phone_country_dial'] ?? null;
        $params['country'] = $params['country'] ?? null;
        $params['state'] = $params['state'] ?? null;
        $params['city'] = $params['city'] ?? null;
        $params['address'] = $params['address'] ?? null;
        $params['zip'] = $params['zip'] ?? null;
        $params['password'] = $params['password'] ?? null;
        $params['role'] = intval($params['role']) ?? null;
        $params['avatar'] = $params['avatar'] ?? null;

        if (
            ($params['name'] !== null && $params['name'] === $company_user->name) &&
            ($params['email'] !== null && $params['email'] === $company_user->email) &&
            ($params['role'] !== null && $params['role'] === $company_user->role) &&
            ($params['phone'] !== null && $params['phone'] === $company_user->phone) &&
            ($params['phone_country'] !== null && $params['phone_country'] === $company_user->phone_country) &&
            ($params['phone_country_dial'] !== null && $params['phone_country_dial'] === $company_user->phone_country_dial) &&
            ($params['country'] !== null && $params['country'] === $company_user->country) &&
            ($params['state'] !== null && $params['state'] === $company_user->state) &&
            ($params['city'] !== null && $params['city'] === $company_user->city) &&
            ($params['address'] !== null && $params['address'] === $company_user->address) &&
            ($params['zip'] !== null && $params['zip'] === $company_user->zip) &&
            ($params['password'] !== null && Hash::check($params['password'], $company_user->password)) &&
            ($params['avatar'] !== null && $params['avatar'] === $company_user->avatar) ||
            ($params['name'] === null &&
                $params['email'] === null &&
                $params['role'] === null &&
                $params['phone'] === null &&
                $params['phone_country'] === null &&
                $params['phone_country_dial'] === null &&
                $params['country'] === null &&
                $params['state'] === null &&
                $params['city'] === null &&
                $params['address'] === null &&
                $params['zip'] === null &&
                $params['password'] === null &&
                $params['avatar'] === null
            )
        ) {
            $r->messages[] = (object) [
                'type' => 'warning',
                'message' => __('Nothing to update.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        if ($params['email'] !== null && $params['email'] !== $company_user->email) {
            if (($company_user = API::getCompanyUserByEmail($params['email'], true, $r)) instanceof JsonResponse) {
                return $company_user;
            }

            if ($company_user !== null && $company_user->id !== $params['user_id']) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Email address is already in use.'),
                ];
                return response()->json($r, Response::HTTP_UNAUTHORIZED);
            }
        }

        if ($params['role'] !== null && $params['role'] !== $company_user->role) {
            if ($company_user->is_master) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Master user cannot change role.'),
                ];
                return response()->json($r, Response::HTTP_UNAUTHORIZED);
            }
        }

        if ($params['avatar'] !== null && $params['avatar'] !== $company_user->avatar) {
            if (($avatar = API::getMedia($params['avatar'], $r)) instanceof JsonResponse) {
                return $avatar;
            }

            if ($avatar === null) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Avatar does not exist.'),
                ];
                return response()->json($r, Response::HTTP_UNAUTHORIZED);
            }
        } else {
            $params['avatar'] = $company_user->avatar;
        }

        try {
            if ($params['name'] !== null && $params['name'] !== $company_user->name) {
                $company_user->name = $params['name'];
            }

            if ($params['email'] !== null && $params['email'] !== $company_user->email) {
                $company_user->email = $params['email'];
            }

            if ($params['role'] !== null && $params['role'] !== $company_user->role) {
                $company_user->role = $params['role'];
            }

            if ($params['phone'] !== null && $params['phone'] !== $company_user->phone) {
                $company_user->phone = $params['phone'];
            }

            if ($params['phone_country'] !== null && $params['phone_country'] !== $company_user->phone_country) {
                $company_user->phone_country = $params['phone_country'];
            }

            if ($params['phone_country_dial'] !== null && $params['phone_country_dial'] !== $company_user->phone_country_dial) {
                $company_user->phone_country_dial = $params['phone_country_dial'];
            }

            if ($params['country'] !== null && $params['country'] !== $company_user->country) {
                $company_user->country = $params['country'];
            }

            if ($params['state'] !== null && $params['state'] !== $company_user->state) {
                $company_user->state = $params['state'];
            }

            if ($params['city'] !== null && $params['city'] !== $company_user->city) {
                $company_user->city = $params['city'];
            }

            if ($params['address'] !== null && $params['address'] !== $company_user->address) {
                $company_user->address = $params['address'];
            }

            if ($params['zip'] !== null && $params['zip'] !== $company_user->zip) {
                $company_user->zip = $params['zip'];
            }

            if ($params['password'] !== null && !Hash::check($params['password'], $company_user->password)) {
                $company_user->password = Hash::make($params['password']);
            }

            $company_user->avatar = $params['avatar'];

            $company_user->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to update user.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('User has been updated.'),
        ];
        $r->success = true;
        $r->data = (object) [
            'updated_at' => now(),
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function doUpdateUserPassword(Request $request, ?string $user_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60'],
            'user_id' => ['required', 'string', 'uuid', 'size:36'],
            'password' => ['required', 'string', 'min:4', 'max:255'],
            'password_confirmation' => ['required', 'string', 'min:4', 'max:255', 'same:password'],
        ], $request->all(), ['user_id' => $user_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyByToken($params['token'], $r)) instanceof JsonResponse) {
            return $company;
        }

        if (($company_user = API::getCompanyUserById($params['user_id'], $r)) instanceof JsonResponse) {
            return $company_user;
        }

        if ($company_user->company_id !== $company->id) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Invalid user.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        if (Hash::check($params['password'], $company_user->password)) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('New password cannot be the same as the old password.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        try {
            $company_user->password = Hash::make($params['password']);
            $company_user->save();
        } catch (\Exception $e) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Failed to update password.'),
            ];
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Password has been updated.'),
        ];

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doDeleteUser(Request $request, ?string $user_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60'],
            'user_id' => ['required', 'string', 'uuid', 'size:36'],
        ], $request->all(), ['user_id' => $user_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyByToken($params['token'], $r)) instanceof JsonResponse) {
            return $company;
        }

        if (($company_user = API::getCompanyUserById($params['user_id'], $r)) instanceof JsonResponse) {
            return $company_user;
        }

        if ($company_user->company_id !== $company->id) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Invalid user.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        if ($company_user->is_master === true) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Owner cannot be deleted, please transfer ownership first.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        $deleted_at = now();

        try {
            $company_user->deleted_at = $deleted_at->format('Y-m-d H:i:s.u');
            $company_user->save();
        } catch (\Exception $e) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Failed to delete user.'),
            ];
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('User has been deleted.'),
        ];

        $r->success = true;
        $r->data = (object) [
            'deleted_at' => $deleted_at,
        ];
        return response()->json($r, Response::HTTP_OK);
    }
}
