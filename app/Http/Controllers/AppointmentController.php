<?php

namespace App\Http\Controllers;

use App\Models\opd_appointment;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function fetchSlots(Request $request)
    {

        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');

        $query = "SELECT 
                * 
            FROM opd_timeslots
            INNER JOIN opd_dateslots 
                on opd_dateslots.id = opd_timeslots.opddateslotsid 
            WHERE opd_dateslots.date = ?
                AND opd_timeslots.opdtimeid = ?
                AND opd_timeslots.type = ?
                AND opd_dateslots.opdserviceid = ?
        ";

        $params = [
            $request->SelectedDate,
            $request->SelectedTime,
            $request->PurposeOfAppointment,
            $request->TypeOfService,
        ];

        $result = $opddb->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchNumberOfPatients(Request $request)
    {
        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');
        $condition = '';
        $conditionParams = [];

        if ($request->opdserviceid == 82) {
            $condition = "AND purpose = ?";
            $conditionParams[] = $request->type;
        } else {
            $condition = "AND (reservationcode = '' OR reservationcode IS NULL)";
        }

        $query = "SELECT 
                COUNT(*) as countSlot 
            FROM `opd_appointment`
            WHERE status = 0
                AND datesked = ?
                AND timesked = ?
                AND service = ?
                $condition
        ";

        $params = [
            $request->date,
            $request->opdtimeid,
            $request->opdserviceid,
            ...$conditionParams,
        ];

        $result = $opddb->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function AppointmentChecker(Request $request)
    {
        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');
        $condition = '';
        $conditionParams = [];

        if ($request->MiddleName) {
            $condition = "AND middlename LIKE ?";
            $conditionParams[] =  '%' . $request->MiddleName  . '%';
        }

        $query = "SELECT 
                * 
            FROM opd_appointment 
            WHERE lastname LIKE ?
                and firstname LIKE ?
                and datesked = ? 
                and status = 0             
                $condition
        ";

        $params = [
            '%' . $request->LastName . '%',
            '%' . $request->FirstName . '%',
            $request->SelectedDate,
            ...$conditionParams,
        ];

        $result = $opddb->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function slotsChecker(Request $request)
    {
        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');

        $query = "SELECT 
                * 
            FROM opd_timeslots 
            WHERE opddateslotsid = ?
                AND opdtimeid = ?
                AND type = ?
        ";

        $params = [
            $request->SelectedDateID,
            $request->SelectedTime,
            $request->PurposeOfAppointment,
            // $request->TypeOfService,
        ];

        $result = $opddb->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function saveAppointment(Request $request)
    {
        $result = [];
        $errors = [];
        $opddb = DB::connection('opddb');
        $opddb->beginTransaction();

        try {
            $request->validate([
                'Barangay' => 'required',
                'ChiefComplaint' => 'nullable',
                'City' => 'required',
                'CivilStatus' => 'required',
                'ContactNumber' => 'required',
                'DateOfBirth' => 'required',
                'FirstName' => 'required',
                'Gender' => 'required',
                'LastName' => 'required',
                'MiddleName' => 'nullable',
                'Nationality' => 'required',
                'Occupation' => 'required',
                'PatientNo' => 'nullable',
                'PlaceOfBirth' => 'required',
                'Province' => 'required',
                'PurposeOfAppointment' => 'required',
                'Religion' => 'required',
                'SelectedDate' => 'required',
                'SelectedDateID' => 'required',
                'SelectedTime' => 'required',
                'Street' => 'required',
                'TypeOfService' => 'required',
            ]);


            $appointment = opd_appointment::create([
                'lastname'    => strtoupper($request->LastName),
                'firstname'   => strtoupper($request->FirstName),
                'middlename'  => strtoupper($request->MiddleName),
                'gender'      => strtoupper($request->Gender),
                'cstat'       => $request->CivilStatus,
                'religion'    => $request->Religion,
                'nationality' => $request->Nationality,
                'pob'         => strtoupper($request->PlaceOfBirth),
                'occupation'  => $request->Occupation,
                'street'      => strtoupper($request->Street),
                'province'    => $request->Province,
                'city'        => $request->City,
                'barangay'    => $request->Barangay,
                'chiefc'      => strtoupper($request->ChiefComplaint),
                'purpose'     => $request->PurposeOfAppointment,
                'contact'     => $request->ContactNumber,
                'datesked'    => $request->SelectedDate,
                'birthday'    => $request->DateOfBirth,
                'timesked'    => $request->SelectedTime,
                'service'     => $request->TypeOfService,

                'patientno'  => $request->PatientNo ?? 0,
                'newold'     => 0,
                'status'     => 0,
                'addedby'    => $request->EmpID,
                'pisid'      => $request->EmpID,
                'dateadded'  => now(),
            ]);

            $firstname = strtoupper(substr($appointment->firstname, 0, 1));
            $middlename = strtoupper(substr($appointment->middlename, 0, 1));
            $lastname = strtoupper(substr($appointment->lastname, 0, 1));
            $id = $appointment->id;

            $reservationCode = $firstname . $middlename . $lastname . $id;

            $appointment->reservationcode = $reservationCode;
            $appointment->save();

            $opddb->commit();

            return response()->json([
                'status' => empty($errors),
                'data' => $result,
                'errors' => $errors,
            ], empty($errors) ? 200 : 500);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            $opddb->rollBack();
        } finally {
            return response()->json([
                'status' => empty($errors),
                'data' => $result,
                'errors' => $errors,
            ], empty($errors) ? 200 : 500);
        }
    }
}
