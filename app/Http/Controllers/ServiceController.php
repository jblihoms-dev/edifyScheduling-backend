<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function EncryptDataSha256($DataString = null)
    {
        if ($DataString == null) {
            return null;
        }
        return hash('sha256', $DataString);
    }

    public function EncryptData($DataString = null)
    {
        if ($DataString == null) {
            return null;
        }
        return Crypt::encrypt($DataString);
    }

    public function DecryptData($DataString = null)
    {

        if ($DataString == null) {
            return null;
        }

        try {
            return Crypt::decrypt($DataString);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function GetUserData($UserID = null, $MedixID = null)
    {

        $UserIDCondition = "";
        $MedixIDCondition = "";
        $errors = [];
        $result = null;


        $params = [];

        if ($UserID) {
            $UserID = is_array($UserID) ? $UserID : [$UserID];
            $UserID = array_filter($UserID);
            $Placeholders = implode(',', $UserID);
            $UserIDCondition = "AND us.id IN ($Placeholders)";
        }

        if ($MedixID) {
            $MedixIDCondition = "AND us.medixid = ?";
            $params[] = $MedixID;
        }

        if (count($UserID) == 0 && !$MedixID) {
            throw new Exception('No UserID or MedixID given.');
        }

        $sql = DB::connection('mysql');


        $query = "SELECT
            us.username as Username
            , us.id
            , CASE 
                WHEN pay.suffix IS NOT NULL AND pay.suffix != '' 
                THEN CONCAT(pay.name, ' ', pay.mname, ' ', pay.lname, ', ', pay.suffix) 
                ELSE CONCAT(pay.name, ' ', pay.mname, ' ', pay.lname) 
            END AS FullName
            , pos.`position` as PositionName
            , dept.department as DepartmentName
            , dept.id as DepartmentID
            , CONCAT('uploads/' , card.id, '/' , us.id, '.jpg') as PhotoLink
            ,  CASE 
                WHEN pay.positionid IN (41, 18, 34, 35, 36) OR (pay.positionid BETWEEN 23 AND 25) OR (pay.positionid BETWEEN 47 AND 57) OR (pay.positionid BETWEEN 58 AND 62) THEN 1 
                ELSE 0 
            END AS isDoctor
            , CASE
                WHEN pay.department IN (95) THEN 1
                ELSE 0 
            END AS isHIMS
            , card.photo as ProfilePhoto
            , card.signature as Signature
            FROM user us 
            INNER JOIN payroll pay on pay.id = us.id
            INNER JOIN `position` pos on pos.positionid = pay.positionid
            INNER join department dept on dept.id = pay.department
            INNER join idcard card on card.idno = us.id and card.status = 0
            WHERE
            1 = 1
            $UserIDCondition
            $MedixIDCondition
        ";

        $result = $sql->select($query, $params);
        foreach ($result as &$row) {
            $row->Drive = md5('profile');
        }

        return $result;
    }

    public function GetMedixID($UserID)
    {

        $sql = DB::connection('mysql');


        $query = "SELECT medixid FROM user WHERE id = ?";
        $params = [$UserID];
        $getMedix = $sql->select($query, $params);

        // if (count($getMedix) > 0) {
        return $getMedix[0]->medixid ?? null;
        // }
    }

    public function getMappedHistory($UserID = null, &$history) // notice the &
    {
        if (!$UserID) return null;

        if (isset($history[$UserID])) {
            return $history[$UserID];
        } else {
            $newUser = $this->GetUserData($UserID);
            if (count($newUser) > 0) {
                $history[$newUser[0]->id] = $newUser[0]->FullName;
                return $newUser[0]->FullName;
            }
        }

        return null;
    }

    public function fetchEmployeeInformation(Request $request)
    {
        $errors = [];
        $sql = DB::connection('mysql');
        $result = null;

        $query = "SELECT 
                p.id
                ,p.name
                ,p.mname
                ,p.lname
                ,ic.gender
                ,ic.contact
                ,ic.bday
                ,pr.cs
                ,r.description as religion  
            FROM payroll p 
            INNER JOIN profile pr 
                ON pr.idno = p.id 
            INNER JOIN idcard ic 
                ON ic.idno = p.id
            LEFT JOIN religion r 
                ON  r.id = ic.religionid 
            WHERE p.id = ? 
        ";

        $params = [
            $request->id,
        ];

        $result = $sql->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchPatientNo(Request $request)
    {
        $errors = [];
        $sql = DB::connection('medix');
        $result = null;
        $condition = '';
        $conditionParams = [];

        if ($request->MiddleName) {
            $condition = "AND pinfo.MiddleName LIKE ?";
            $conditionParams[] = '%' . $request->MiddleName . '%';
        }

        $query = "SELECT TOP 1
                pt.PatientNo
            FROM PatientHistory ph (nolock)  
            INNER JOIN PatientInfo pinfo  (nolock)  
                ON pinfo.PatientHistoryID = ph.PatientHistoryID
            INNER JOIN patients pt (nolock)  
                ON pt.patientID = ph.PatientID
            INNER JOIN persons per (nolock)  
                ON per.PersonID = pinfo.PersonID
            where ph.Status = 1 
                AND pinfo.LastName LIKE ? 
                AND pinfo.FirstName LIKE ? 
                $condition
            ORDER BY ph.PatientHistoryID
        ";

        $params = [
            '%' . $request->LastName . '%',
            '%' . $request->FirstName . '%',
            ...$conditionParams,
        ];

        $result = $sql->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchCivilStatus()
    {
        $errors = [];
        $sql = DB::connection('medix');
        $result = null;

        $query = "SELECT 
            civilstatusid
            , name
            FROM civilstatus 
            ORDER BY civilstatusid ASC
        ";

        $params = [];

        $result = $sql->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }
    public function fetchReligion()
    {
        $errors = [];
        $sql = DB::connection('medix');
        $result = null;

        $query = "SELECT 
                id
                , description 
            FROM religion 
            ORDER BY id ASC
        ";

        $params = [];

        $result = $sql->select($query, $params);
        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }
    public function fetchNationality()
    {
        $errors = [];
        $sql = DB::connection('medix');
        $result = null;

        $query = "SELECT 
                id
                , description
            FROM nationality 
            ORDER BY description ASC
        ";

        $params = [];

        $result = $sql->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }
    public function fetchOccupation()
    {
        $errors = [];
        $sql = DB::connection('medix');
        $result = null;

        $query = "SELECT 
                occupationid
                , description
            FROM occupation   
            ORDER BY occupationid ASC
        ";

        $params = [];

        $result = $sql->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }
    public function fetchProvince()
    {
        $errors = [];
        $sql = DB::connection('medix');
        $result = null;

        $query = "SELECT 
                provinceid
                , description
            FROM province  
            ORDER BY description ASC
        ";

        $params = [];

        $result = $sql->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }
    public function fetchMunicipality(Request $request)
    {
        $errors = [];
        $result = null;
        $sql = DB::connection('medix');
        $condition = '';
        $conditionParams = [];

        if ($request->ProvinceID) {
            $condition = 'WHERE ProvinceID = ?';
            $conditionParams[] = $request->ProvinceID;
        }

        $query = "SELECT 
                MunicipalityID
                , description
            FROM Municipality 
            $condition  
            ORDER BY description ASC
        ";

        $params = [$request->ProvinceID];

        $result = $sql->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchBarangay(Request $request)
    {
        $errors = [];
        $result = null;
        $sql = DB::connection('medix');
        $condition = '';
        $conditionParams = [];

        if ($request->MunicipalityID) {
            $condition = 'WHERE MunicipalityID = ?';
            $conditionParams[] = $request->MunicipalityID;
        }

        $query = "SELECT 
                Id
                , Name
            FROM barangay    
            $condition         
            ORDER BY name ASC
        ";

        $params = [...$conditionParams];

        $result = $sql->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchPurposeOfAppointment(Request $request)
    {
        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');

        $query = "SELECT 
                * 
            FROM ehwc_appointmentpurpose 
            WHERE status = 1 
            AND type = 1 
            ORDER BY description
        ";

        $params = [];

        $result = $opddb->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchTypeOfService(Request $request)
    {
        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');

        $query = "SELECT 
                id
                , description
            FROM opd_service 
            ORDER BY description
        ";

        $params = [];

        $result = $opddb->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchAppointmentSlots(Request $request)
    {

        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');

        $query = "SELECT *, d.date AS datesched 
            FROM opd_dateslots d 
            INNER JOIN opd_timeslots t ON t.opddateslotsid = d.id 
            LEFT JOIN opd_holidays h ON d.date = h.date AND h.status = 1 
            WHERE opdserviceid = ?
            AND h.date IS NULL
            AND t.type = ?
            AND d.status = 1
            -- AND d.date >= CURDATE(); -- add this to live
        ";

        $params = [
            $request->TypeOfService,
            $request->PurposeOfAppointment
        ];

        $result = $opddb->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchNumberOfPatients($date, $timeId, $serviceId, $type = null)
    {
        $opddb = DB::connection('opddb');

        $condition = '';
        $params = [$date, $timeId, $serviceId];

        if ($serviceId == 82) {
            $condition = "AND purpose = ?";
            $params[] = $type;
        } else {
            $condition = "AND (reservationcode = '' OR reservationcode IS NULL)";
        }

        $query = "SELECT 
                COUNT(id) as countSlot
            FROM opd_appointment
            WHERE status = 0
                AND datesked = ?
                AND timesked = ?
                AND service = ?
                $condition
        ";

        return $opddb->select($query, $params)[0]->countSlot ?? 0;
    }

    public function fetchAppointmentTime(Request $request)
    {

        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');
        $condition = '';
        $conditionParams = [];

        $currDate = date('Y-m-d');
        $currTime = date('H:i:s');

        if ($request->OPDDate == $currDate) {
            $condition = " AND opd_time.opdtime > ? ";
            $conditionParams[] = $currTime;
        }

        $query = "SELECT 
                 date
                , opdtimeid
                , opdserviceid
                , type
                , slots
                , TIME_FORMAT(opd_time.opdtime, '%h:%i:%s %p') as time 
            FROM opd_timeslots 
            INNER JOIN opd_time on opd_time.id = opd_timeslots.opdtimeid
            INNER JOIN opd_dateslots on opd_dateslots.id = opd_timeslots.opddateslotsid
            WHERE opd_dateslots.date = ?
                AND opd_dateslots.opdserviceid = ?
                AND opd_timeslots.type = ?
                $condition
            ORDER BY opd_time.opdtime;  
        ";

        $params = [
            $request->OPDDate,
            $request->OPDServiceID,
            $request->PurposeOfAppointment,
            ...$conditionParams,
        ];

        $result = $opddb->select($query, $params);

        foreach ($result as $time) {
            $slotCount = $this->fetchNumberOfPatients(
                $time->date,
                $time->opdtimeid,
                $time->opdserviceid,
                $time->type ?? null
            );

            $remaining = $time->slots - $slotCount;

            $time->remaining = $remaining ?? 0;
        }

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }

    public function fetchPatientInformation(Request $request)
    {
        $errors = [];
        $result = null;
        $opddb = DB::connection('opddb');

        $condition = '';
        $conditionParams = [];

        if ($request->MiddleName) {
            $condition = "AND middlename LIKE ?";
            $conditionParams[] = '%' . $request->MiddleName . '%';
        }

        $query = "SELECT 
                * 
            FROM `opd_appointment` 
            WHERE lastname LIKE ?
                AND firstname LIKE ?
                $condition
                AND street is not null 
                AND province is not null 
                AND city is not null 
                AND barangay is not null 
                AND status = 0 ORDER BY id DESC LIMIT 1
        ";

        $params = [
            '%' . $request->LastName  . '%',
            '%' . $request->FirstName  . '%',
            ...$conditionParams,
        ];

        $result = $opddb->select($query, $params);

        return response()->json([
            'status' => empty($errors),
            'data' => $result,
            'errors' => $errors,
        ], empty($errors) ? 200 : 500);
    }
}
