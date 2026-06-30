<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Authentication;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ViewAppointmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/Version', function () {
    return response()->json(['status' => true, 'data' => ['version' => config('app.current_version')], 'errors' => []]);
});

Route::prefix('Auth')->group(function () {
    Route::post('/Login', [Authentication::class, 'AuthPayroll']);
    Route::post('/Route', [Authentication::class, 'AuthCheckToken']);
});

Route::prefix('File')->group(function () {
    Route::get('/Image/{drive}/{path}', [FileController::class, 'FetchImage']);
    Route::get('/View', [FileController::class, 'ViewFile']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('User')->group(function () {
        Route::get('/fetchUser', [UserController::class, 'fetchUserInfo']);
    });

    Route::prefix('Auth')->group(function () {
        Route::post('/Logout', [Authentication::class, 'logout']);
    });

    Route::prefix('Service')->group(function () { 
        Route::get('/fetchEmployeeInformation', [ServiceController::class, 'fetchEmployeeInformation']);
        Route::get('/fetchPatientNo', [ServiceController::class, 'fetchPatientNo']);
        Route::get('/fetchPatientInformation', [ServiceController::class, 'fetchPatientInformation']);
        Route::get('/fetchCivilStatus', [ServiceController::class, 'fetchCivilStatus']);
        Route::get('/fetchReligion', [ServiceController::class, 'fetchReligion']);
        Route::get('/fetchNationality', [ServiceController::class, 'fetchNationality']);
        Route::get('/fetchOccupation', [ServiceController::class, 'fetchOccupation']);
        Route::get('/fetchProvince', [ServiceController::class, 'fetchProvince']);
        Route::get('/fetchMunicipality', [ServiceController::class, 'fetchMunicipality']);
        Route::get('/fetchBarangay', [ServiceController::class, 'fetchBarangay']);
        Route::get('/fetchPurposeOfAppointment', [ServiceController::class, 'fetchPurposeOfAppointment']);
        Route::get('/fetchTypeOfService', [ServiceController::class, 'fetchTypeOfService']);
        Route::get('/fetchAppointmentSlots', [ServiceController::class, 'fetchAppointmentSlots']);
        Route::get('/fetchAppointmentTime', [ServiceController::class, 'fetchAppointmentTime']);
    });

    Route::prefix('Appointment')->group(function () {
        Route::get('/fetchSlots', [AppointmentController::class, 'fetchSlots']);
        Route::get('/fetchNumberOfPatients', [AppointmentController::class, 'fetchNumberOfPatients']);
        Route::get('/AppointmentChecker', [AppointmentController::class, 'AppointmentChecker']);
        Route::get('/slotsChecker', [AppointmentController::class, 'slotsChecker']);

        Route::post('/saveAppointment', [AppointmentController::class, 'saveAppointment']);
        Route::post('/sendSMS', [AppointmentController::class, 'sendSMS']);
    });

    Route::prefix('Viewing')->group(function () {
        Route::get('/fetchPatient', [ViewAppointmentController::class, 'fetchPatient']);
        Route::get('/fetchPatientAppointments', [ViewAppointmentController::class, 'fetchPatientAppointments']);
        Route::get('/fetchPatientAppointmentCount', [ViewAppointmentController::class, 'fetchPatientAppointmentCount']);
        
        Route::post('/cancelAppointment', [ViewAppointmentController::class, 'cancelAppointment']);
    });
});
