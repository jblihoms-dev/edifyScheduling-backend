<?php

namespace App\Http\Controllers;

use App\Models\opd_appointment;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


use Illuminate\Http\Request;

class ViewAppointmentController extends Controller
{
    public function fetchPatient(Request $request)
    {
        $errors = [];
        $result = null;
        $mysql = DB::connection('mysql');

        $query = "SELECT 
                CONCAT(idcard.fname, ' ', idcard.mname, ' ', idcard.lname, ' ', idcard.suffix) as empname
                , idcard.position
                , idcard.gender
                , idcard.bday
                , idcard.bloodtype
                , idcard.photo
                , profile.cs
                , profile.age 
            FROM idcard 
            LEFT JOIN profile ON idcard.id = profile.idno 
            WHERE idcard.idno = ?
        ";

        $params = [
            $request->UserID,
        ];

        $result = $mysql->select($query, $params);

        foreach ($result as $p) {
            $p->drive = md5('profile');
        }

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchPatientAppointments(Request $request)
    {
        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');

        $query = "SELECT 
                opd_appointment.lastname 
                , opd_appointment.id 
                , opd_appointment.firstname 
                , opd_appointment.gender 
                , opd_appointment.middlename 
                , DATE_FORMAT(
                    opd_appointment.dateadded,  '%M %e, %Y %h:%i %p'
                ) as dateadded
                , DATE_FORMAT(
                    opd_appointment.datesked, '%M %e, %Y'
                ) AS datesched 
                , TIME_FORMAT(opd_time.opdtime, '%h:%i %p') AS timesched 
                , opd_service.description as servicetype 
                , opd_appointment.reservationcode
                , opd_appointment.status
                , opd_appointment.cancelledby
                , opd_appointment.cancelleddatetime
                , DATE_FORMAT(
                    opd_appointment.cancelleddatetime,  '%M %e, %Y %h:%i %p'
                ) as cancelleddatetime
                , opd_appointment.edify
            FROM opd_appointment 
            INNER JOIN opd_time 
                ON opd_time.id = opd_appointment.timesked 
            INNER JOIN opd_service 
                ON opd_service.id = opd_appointment.service 
            WHERE 
                pisid = ?
                AND service = 82
                ORDER BY datesked DESC
                LIMIT ? OFFSET ?
        ";

        $params = [
            $request->UserID,
            intval($request->pagelength),
            intval($request->page * $request->pagelength),
        ];

        $result = $opddb->select($query, $params);

        $ServiceController = new ServiceController();

        foreach ($result as $r) {
            $r->cancelledbyname = '';

            if ($r->cancelledby) {
                $cancelledbyname = $ServiceController->GetUserData($r->cancelledby);
                $r->cancelledbyname = $cancelledbyname[0]->FullName;
            }
        }

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchPatientAppointmentCount(Request $request)
    {
        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');

        $query = "SELECT 
                COUNT(opd_appointment.id) as TotalCount
            FROM opd_appointment 
            INNER JOIN opd_time 
                ON opd_time.id = opd_appointment.timesked 
            INNER JOIN opd_service 
                ON opd_service.id = opd_appointment.service 
            WHERE 
                pisid = ?
                AND service = 82
        ";

        $params = [
            $request->UserID,
        ];

        $result = $opddb->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function cancelAppointment(Request $request)
    {
        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');
        $opddb->beginTransaction();

        try {
            $datetime = now();

            $scheduleDateTime = Carbon::parse("$request->datesched $request->timesched");

            if ($datetime->gt($scheduleDateTime)) {
                throw new Exception('The scheduled date and time has already passed.');
            }

            $query = "UPDATE opd_appointment 
            SET 
                status = 1
                , cancelledby = ?
                , cancelleddatetime = ?
            WHERE 
                id = ?                
        ";

            $params = [
                $request->UserID,
                $datetime,
                $request->id,
            ];

            $result = $opddb->update($query, $params);

            $opddb->commit();
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
