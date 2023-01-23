<?php

namespace App\Http\Controllers;

use App\Models\LiveStreamCompanies as mLiveStreamCompanies;
use App\Models\PasswordResets as mPasswordResets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Account extends API
{
    public function doLogin(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:4', 'max:255'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyAccountByEmail($r, $params['email'])) instanceof JsonResponse) {
            return $company;
        }

        if (!$company->hasVerifiedEmail()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Email address is not verified.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        if (!Hash::check($params['password'], $company->password)) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Invalid password.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        $r->success = true;
        $r->data = (object) [
            'id' => $company->id,
            'name' => $company->name,
            'token' => $company->generateToken(),
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function doLogout(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyAccountByToken($r, $params['token'])) instanceof JsonResponse) {
            return $company;
        }

        $company->token = null;
        $company->save();

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doResetPassword(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'email' => ['required', 'email', 'max:255'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        // Get company account - Skip check account exists
        if (($company = API::getCompanyAccountByEmail($r, $params['email'], true)) instanceof JsonResponse) {
            return $company;
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('If the email address is valid, you will receive an email with a link to reset your password.'),
        ];
        $r->success = true;

        if ($company === null || !$company->hasVerifiedEmail()) {
            return response()->json($r, Response::HTTP_OK);
        }

        $token = Str::random(80);
        $reset = new mPasswordResets();
        $reset->email = $company->email;
        $reset->token = $token;
        $reset->created_at = now()->format('Y-m-d H:i:s');
        $reset->save();

        return response()->json($r, Response::HTTP_OK);
    }

    public function doResetPasswordVerify(Request $request, ?string $token = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:80'],
            'password' => ['required', 'string', 'min:4', 'max:255'],
            'password_confirmation' => ['required', 'string', 'min:4', 'max:255', 'same:password'],
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

        if (strtotime($reset->created_at) < strtotime('-30 minutes')) {
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

        if ($params['password'] !== $params['password_confirmation']) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Passwords do not match.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        if (($company = API::getCompanyAccountByEmail($r, $reset->email)) instanceof JsonResponse) {
            return $company;
        }

        if (Hash::check($params['password'], $company->password)) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('New password cannot be the same as the old password.'),
            ];
            return response()->json($r, Response::HTTP_UNAUTHORIZED);
        }

        try {
            $company->password = Hash::make($params['password']);
            $reset->delete();
        } catch (\Exception $e) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Failed to reset password.'),
            ];
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Password has been reset.'),
        ];
        $r->data = (object) [
            'id' => $company->id,
            'name' => $company->name,
            'token' => $company->generateToken(),
        ];
        return response()->json($r, Response::HTTP_OK);
    }
}
