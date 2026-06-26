<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;


class Authentication extends Controller
{
    public function OpenSSLDecrypt($DataString)
    {
        $UniquePassword = substr(hash('sha256', "password" . date('Y-m-d'), true), 0, 16);
        return openssl_decrypt(urldecode(str_replace("+", "%2B", urlencode($DataString))), "AES-128-ECB", $UniquePassword);
    }

    public function OpenSSLEncrypt($DataString)
    {
        $UniquePassword = substr(hash('sha256', "password" . date('Y-m-d'), true), 0, 16);
        return openssl_encrypt($DataString, "AES-128-ECB", $UniquePassword);
    }

    public function AuthPayroll(Request $request)
    {
        $request->validate(
            [
                'username' => 'required|string',
                'password' => 'required|string',
            ],
            [
                'username.required' => 'Username is Required to proceed',
                'password.required' => 'Password is Required to proceed',
            ]
        );

        $result = [];
        $errors = [];

        try {
            $username =  $request->username;
            $password =  md5($request->password);

            if (!$username || !$password) {
                throw new Exception('Missing Username or Password, Retry to Login, ');
            }

            // PAYROLL USERS
            $user = User::join('payroll as pay', 'pay.id', '=', 'user.id')
                ->join('position as pos', 'pos.positionid', '=', 'pay.positionid')
                ->join('department as dep', 'dep.id', '=', 'pay.department')
                ->selectRaw("
                        user.id,
                        user.id as UserID,
                        user.medixid as MedixID,
                        user.username,
                        pay.idnumber,
                        CONCAT(pay.name, ' ', pay.mname, ' ', pay.lname, ' ', pay.suffix) as FullName,
                        pay.status as PayrollStatus,
                        pay.positionid as PositionID,
                        pos.position as PositionName,
                        pay.department as DepartmentID,
                        dep.department as DepartmentName
                    ")
                ->where('user.username', $username)
                ->where('user.password', $password)
                ->first();

            if ($user == null) {
                $errors[] = "Username or Password doesn't match";
            } else {

                if ($user->PayrollStatus !== 'A') {
                    $errors[] = "Account is No Longer Active, Contact IHOMS For Support";
                }

                $tokenInstance = $user->createToken(config('app.token_name'));
                $tokenInstance->accessToken->expires_at = Carbon::now()->addMinutes(config('sanctum.expiration'))->format('Y-m-d H:i:s');
                $tokenInstance->accessToken->save();
                $user->Token = $tokenInstance->plainTextToken;
                $result = [$user];

                if (count($result) == 0) {
                    $errors[] = "User does not have permission to Access this site";
                }
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        } finally {
            return response()->json([
                'status' => empty($errors),
                'data' => $result,
                'errors' => $errors,
            ], empty($errors) ? 200 : 500);
        }
    }

    public function AuthLogout(Request $request)
    {
        try {

            $errors = [];
            $user = Auth::user();
            $token = $user->tokens()->where('name', config('app.token_name'))->first();

            if ($token) {
                $token->delete();
            } else {
                $errors[] = 'Token Not Existing';
            }
        } catch (\Exception $e) {

            $errors[] = $e->getMessage();
        } finally {
            return response()->json([
                'status' => empty($errors),
                'data' => $token,
                'errors' => $errors,
            ], empty($errors) ? 200 : 500);
        }
    }

    public function AuthCheckToken(Request $request)
    {
        $errors = [];
        $result = [false];
        try {

            $user = null;

            $authHeader = $request->header('Authorization');
            $bearerToken = $authHeader ? trim(str_replace('Bearer ', '', $authHeader)) : null;

            if (!$bearerToken) {
                $errors[] = 'No Token Available';
            } else {
                $user = Auth::guard('sanctum')->user();
                if (!$user) {
                    $errors[] = 'Invalid Token';
                }
            }

            if (count($errors) == 0) {
                $result = [true];
            }
        } catch (\Exception $e) {

            $errors[] = $e->getMessage();
        } finally {
            return response()->json([
                'status' => empty($errors),
                'data' => $result,
                'errors' => $errors,
            ], empty($errors) ? 200 : 500);
        }
    }
}
