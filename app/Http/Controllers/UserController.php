<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class UserController extends Controller
{


    public function fetchUserInfo(Request $request)
    {

        $request->validate(
            [
                'UserID' => 'required|numeric',
                'MedixID' => 'required|numeric'
            ],
            [
                'UserID.required' => 'UserID is Required to proceed',
                'MedixID.required' => 'MedixID is Required to proceed',
            ]
        );

        try {

            $result = null;
            $errors = [];

            $ServiceController = new ServiceController();
            $result = $ServiceController->GetUserData($request->UserID, $request->MedixID);
            if (count($result) <= 0) {
                $errors[] = "User Information is Not Existing";
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
