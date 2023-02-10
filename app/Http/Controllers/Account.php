<?php

namespace App\Http\Controllers;

use App\Mail\ResetPassword;
use App\Models\LiveStreamCompanies as mLiveStreamCompanies;
use App\Models\LiveStreamCompanyUsers as mLiveStreamCompanyUsers;
use App\Models\PasswordResets as mPasswordResets;
use App\Models\Tenants as mTenants;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class Account extends API
{
    public function doCreate(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'tenant_id' => ['nullable', 'uuid', 'size:32'],
            'name' => ['required', 'string', 'min:4', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
            'phone_country' => ['required', 'string', 'min:2', 'max:4'],
            'phone' => ['required', 'string', 'min:4', 'max:32'],
            'brand_name' => ['required', 'string', 'min:4', 'max:110'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'password_confirmation' => ['nullable', 'string', 'min:6', 'max:100'],
            'terms' => ['nullable', new strBoolean],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (isset($params['phone_country']) && !preg_match('/^[A-Za-z]+$/', $params['phone_country'])) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Phone country code must be only letters.'),
            ];
            return response()->json($r, Response::HTTP_UNPROCESSABLE_ENTITY);
        } else {
            $params['phone_country'] = strtoupper($params['phone_country']);
        }

        if (isset($params['phone']) && !preg_match('/^[0-9]+$/', $params['phone'])) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Phone number must be only numbers.'),
            ];
            return response()->json($r, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (isset($params['password_confirmation']) && $params['password'] !== $params['password_confirmation']) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Password confirmation does not match.'),
            ];
            return response()->json($r, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (isset($params['terms'])) {
            $params['terms'] = filter_var($params['terms'], FILTER_VALIDATE_BOOLEAN);
            if (!$params['terms']) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('You must agree to the terms and conditions.'),
                ];
                return response()->json($r, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (mLiveStreamCompanyUsers::where('email', '=', $params['email'])->exists()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Email address already taken.'),
            ];
            return response()->json($r, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (mLiveStreamCompanyUsers::where('phone', '=', $params['phone'])->exists()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Phone number already taken.'),
            ];
            return response()->json($r, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (mLiveStreamCompanies::where('name', '=', $params['brand_name'])->exists()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Brand name already taken.'),
            ];
            return response()->json($r, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $params['tenant_id'] = $params['tenant_id'] ?? '2278df21-2f4f-40dd-918a-6650eb1e3e91';

        if (!mTenants::where('id', '=', $params['tenant_id'])->exists()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Tenant does not exist.'),
            ];
            return response()->json($r, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $company_id = Str::uuid()->toString();

        try {
            $company = new mLiveStreamCompanies;
            $company->id = $company_id;
            $company->tenant_id = $params['tenant_id'];
            $company->name = $params['brand_name'];
            $company->save();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to create company.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = [
                    'message' => $e->getMessage(),
                ];
            }
            $r->messages[] = (object) $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $company_user_id = Str::uuid()->toString();
            $company_user = new mLiveStreamCompanyUsers;
            $company_user->id = $company_user_id;
            $company_user->role = 1;
            $company_user->company_id = $company_id;
            $company_user->email = $params['email'];
            $company_user->name = $params['name'];
            $company_user->password = Hash::make($params['password']);
            $company_user->phone_country_code = $params['phone_country'];
            $company_user->phone = $params['phone'];
            $company_user->is_master = true;
            $company_user->save();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to create user account.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = [
                    'message' => $e->getMessage(),
                ];
            }
            $r->messages[] = (object) $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $company_user = mLiveStreamCompanyUsers::find($company_user_id);
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

        $r->data = (object) [
            'token' => $token,
            'token_expires_at' => $token_expires_at->toDateTimeString(),
            'company_id' => $company_id,
            'company_user_id' => $company_user_id,
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

        if (!$company_user->hasVerifiedEmail()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Email address is not verified.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

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

        $r->success = true;
        $r->data = (object) [
            'id' => $company->id,
            'name' => $company->name,
            'role' => $company_user->role,
            'avatar' => $company->getAvatar(),
            'logo' => $company->getLogo(),
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
            Mail::to($company_user->email)->send(new ResetPassword($company_user, $token));
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to send email.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }


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

    public function getUsers(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyByToken($params['token'], $r)) instanceof JsonResponse) {
            return $company;
        }

        $r->success = true;
        $r->data = $company->users()->get()->map(function ($user) {
            $user->makeVisible(['role', 'is_master', 'phone', 'created_at']);
            return (object) [
                'id' => $user->id,
                'created_at' => $user->created_at,
                'email_verified' => $user->hasVerifiedEmail(),
                'email' => $user->email,
                'is_master' => $user->is_master,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role,
            ];
        });
        return response()->json($r, Response::HTTP_OK);
    }

    public function doUpdateUser(Request $request, ?string $user_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60'],
            'user_id' => ['required', 'string', 'uuid', 'size:36'],
            'name' => ['required', 'string', 'min:1', 'max:110'],
            'email' => ['nullable', 'string', 'email', 'min:1', 'max:255'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
            'password_confirmation' => ['nullable', 'string', 'min:4', 'max:255', 'same:password'],
            'role' => ['nullable', 'integer', 'min:0', 'max:9'],
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

        if ($params['email'] !== null && $params['email'] !== $company_user->email) {
            if (($company_user = API::getCompanyUserByEmail($params['email'], $r)) instanceof JsonResponse) {
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

        try {
            $company_user = new mLiveStreamCompanyUsers();
            $company_user->name = $params['name'];
            $company_user->email = $params['email'];
            $company_user->role = $params['role'];
            $company_user->save();
            $company_user->refresh();
        } catch (\Exception $e) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Failed to update user.'),
            ];
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('User has been updated.'),
        ];
        $r->success = true;
        $r->data = $company_user;
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
