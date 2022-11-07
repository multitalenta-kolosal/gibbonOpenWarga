<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Http\Url;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Services\Format;
use Gibbon\Module\Attendance\AttendanceView;
use Gibbon\Domain\Attendance\AttendanceLogPersonGateway;

//Get's a count of absent days for specified student between specified dates (YYYY-MM-DD, inclusive). Return of FALSE means there was an error, or no data
function getAbsenceCount($guid, $gibbonPersonID, $connection2, $dateStart, $dateEnd, $gibbonCourseClassID = 0)
{
    $queryFail = false;

    global $gibbon, $session, $pdo, $container;

    $settingGateway = $container->get(SettingGateway::class);
    require_once __DIR__ . '/src/AttendanceView.php';
    $attendance = new AttendanceView($gibbon, $pdo, $settingGateway);

    //Get all records for the student, in the date range specified, ordered by date and timestamp taken.
    try {
        if (!empty($gibbonCourseClassID)) {
            $data = array('gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'gibbonCourseClassID' => $gibbonCourseClassID);
            $sql = "SELECT gibbonAttendanceLogPerson.*, gibbonSchoolYearSpecialDay.type AS specialDay FROM gibbonAttendanceLogPerson
                    LEFT JOIN gibbonSchoolYearSpecialDay ON (gibbonSchoolYearSpecialDay.date=gibbonAttendanceLogPerson.date AND gibbonSchoolYearSpecialDay.type='School Closure')
                WHERE gibbonPersonID=:gibbonPersonID AND context='Class' AND gibbonCourseClassID=:gibbonCourseClassID AND (gibbonAttendanceLogPerson.date BETWEEN :dateStart AND :dateEnd) ORDER BY gibbonAttendanceLogPerson.date, timestampTaken";
        } else {
            $countClassAsSchool = $settingGateway->getSettingByScope('Attendance', 'countClassAsSchool');
            $data = array('gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd);
            $sql = "SELECT gibbonAttendanceLogPerson.*, gibbonSchoolYearSpecialDay.type AS specialDay
                    FROM gibbonAttendanceLogPerson
                    LEFT JOIN gibbonSchoolYearSpecialDay ON (gibbonSchoolYearSpecialDay.date=gibbonAttendanceLogPerson.date AND gibbonSchoolYearSpecialDay.type='School Closure')
                    WHERE gibbonPersonID=:gibbonPersonID
                    AND (gibbonAttendanceLogPerson.date BETWEEN :dateStart AND :dateEnd)";
                    if ($countClassAsSchool == "N") {
                        $sql .= ' AND NOT context=\'Class\'';
                    }
                    $sql .= " ORDER BY gibbonAttendanceLogPerson.date, timestampTaken";
        }
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        $queryFail = true;
    }

    if ($queryFail) {
        return false;
    } else {
        $absentCount = 0;
        if ($result->rowCount() >= 0) {
            $endOfDays = array();
            $dateCurrent = '';
            $dateLast = '';
            $count = -1;

            //Scan through all records, saving the last record for each day
            while ($row = $result->fetch()) {
                if ($row['specialDay'] != 'School Closure') {
                    $dateCurrent = $row['date'];
                    if ($dateCurrent != $dateLast) {
                        ++$count;
                    }
                    $endOfDays[$count] = $row['type'];
                    $dateLast = $dateCurrent;
                }
            }

            //Scan though all of the end of days records, counting up days ending in absent
            if (count($endOfDays) >= 0) {
                foreach ($endOfDays as $endOfDay) {
                    if ( $attendance->isTypeAbsent($endOfDay) ) {
                        ++$absentCount;
                    }
                }
            }
        }

        return $absentCount;
    }
}

//get today attendance date
function renderAttendanceNow($guid, $connection2, $gibbonPersonID, $title = '')
{
    global $session, $container;

    $zCount = 0;
    $output = "<div style='margin-top: 20px'><span style='font-size: 85%; font-weight: bold'>".__('Today Attendance')."</span> . <span style='font-size: 70%'><a href='" . Url::fromModuleRoute('Attendance', 'report_studentHistory')->withQueryParam('gibbonPersonID', $gibbonPersonID) . "'>".__('View Attendance History').'</a></span></div>';

    $data = array('gibbonPersonID' => $gibbonPersonID);
        $sql = 'SELECT * FROM gibbonPerson WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID ORDER BY surname, preferredName';
        $result = $connection2->prepare($sql);
        $result->execute($data);
    if ($result->rowCount() != 1) {
        $output .= "<div class='error'>";
        $output .= __('The specified person record does not exist.');
        $output .= '</div>';
    } else {
        
        $row = $result->fetch();

        // Get Logs
        global $session, $container;
        
        $attendanceLog = $container->get(AttendanceLogPersonGateway::class);
        $settingGateway = $container->get(SettingGateway::class);

        $today = date('Y-m-d');
        $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
        $crossFillClasses = $settingGateway->getSettingByScope('Attendance', 'crossFillClasses');

        $logs = $attendanceLog
            ->selectAttendanceLogsByPersonAndDate($gibbonPersonID, $today, $crossFillClasses)
            ->fetchGrouped();
        
        $logValueArray = reset($logs);
        if($logValueArray){
            $logValue = reset($logValueArray);
            
            //proccessing the time
            $rawTime  = strtotime($logValue["timestampTaken"]);
            $takenAt = $newformat = date('d-M-Y, H:i',$rawTime);
        }else{
            $logValue = NULL;
            $takenAt = "--";
        }

        $attendanceColor = getColorClass(key($logs));

        $output .= "<div style='margin-top: 2px' class='".$attendanceColor."'>";
        $output .= "<span style='font-size: 200%; font-weight: bold'>";
        $output .= key($logs) ?? "No Data";
        $output .= '</span>';
        $output .= '</div>';
        $output .= "<div style='font-size: 90%; font-weight: normal'>";

        $output .= "<table cellspacing='0' style='margin: 3px 0px; width: 50%'>";
        $output .= "<tr class='odd'>";
        $output .= "<td>";
        $output .= "<span style='font-size: 90%; font-weight: bold'>Reason</span>";
        $output .= "</td>";
        $output .= "<td>";
        $output .= ($logValue["reason"] ?? "--");
        $output .= "</td>";
        $output .= '</tr>';
        $output .= "<tr class='odd'>";
        $output .= "<td>";
        $output .= "<span style='font-size: 90%; font-weight: bold'>Notes</span>";
        $output .= "</td>";
        $output .= "<td>";
        $output .= ($logValue["comment"] ?? "--");
        $output .= "</td>";
        $output .= '</tr>';
        $output .= "<tr class='odd'>";
        $output .= "<td>";
        $output .= "<span style='font-size: 90%; font-weight: bold'>Taken at</span>";
        $output .= "</td>";
        $output .= "<td>";
        $output .= $takenAt;
        $output .= "</td>";
        $output .= '</tr>';
        $output .= '</table>';
        
    }

    return $output;
}

//get color for attendance
function getColorClass($attendanceType){

    $lowercaseAttendance = strtolower($attendanceType);
    
    if(str_contains($lowercaseAttendance,'present') ){
        return 'success';
    }else if(str_contains($lowercaseAttendance,'left') ){
        return 'warning';
    }else if(str_contains($lowercaseAttendance,'absent') ){
        return 'error';
    }else{
        return 'alert';
    }
}

//Get last N school days from currentDate within the last 100
function getLastNSchoolDays( $guid, $connection2, $date, $n = 5, $inclusive = false ) {
    $timestamp = Format::timestamp($date);
    if ($inclusive == true)  $timestamp += 86400;

    $count = 0;
    $spin = 1;
    $max = max($n, 100);
    $lastNSchoolDays = array();
    while ($count < $n and $spin <= $max) {
        $date = date('Y-m-d', ($timestamp - ($spin * 86400)));
        if (isSchoolOpen($guid, $date, $connection2 )) {
            $lastNSchoolDays[$count] = $date;
            ++$count;
        }
        ++$spin;
    }

    return $lastNSchoolDays;
}

//Get's a count of late days for specified student between specified dates (YYYY-MM-DD, inclusive). Return of FALSE means there was an error.
function getLatenessCount($guid, $gibbonPersonID, $connection2, $dateStart, $dateEnd)
{
    global $container;

    $queryFail = false;

    //Get all records for the student, in the date range specified, ordered by date and timestamp taken.
    try {
        $countClassAsSchool = $container->get(SettingGateway::class)->getSettingByScope('Attendance', 'countClassAsSchool');
        $data = array('gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd);
        $sql = "SELECT count(*) AS count
                FROM gibbonAttendanceLogPerson p, gibbonAttendanceCode c
                WHERE (c.scope='Onsite - Late' OR c.scope='Offsite - Late')
                AND p.gibbonPersonID=:gibbonPersonID
                AND p.date>=:dateStart
                AND p.date<=:dateEnd
                AND p.type=c.name";
                if ($countClassAsSchool == "N") {
                    $sql .= ' AND NOT context=\'Class\'';
                }
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        $queryFail = true;
    }

    if ($queryFail) {
        return false;
    } else {
        $row = $result->fetch();
        return $row['count'];
    }
}

function num2alpha($n)
{
    for ($r = ''; $n >= 0; $n = intval($n / 26) - 1) {
        $r = chr($n % 26 + 0x41).$r;
    }

    return $r;
}

function getColourArray()
{
    $return = array();

    $return[] = '153, 102, 255';
    $return[] = '255, 99, 132';
    $return[] = '54, 162, 235';
    $return[] = '255, 206, 86';
    $return[] = '75, 192, 192';
    $return[] = '255, 159, 64';
    $return[] = '152, 221, 95';

    return $return;
}

function composeAttendanceMessage($data, $rowWhatsapp){

$formattedDate = date('d-M-Y' ,strtotime($data['date']));

return '
*PERHATIAN*

Selamat '.composeTimeGreetingsID().' kami dari '.$session->get('organisationName').' menginformasikan bahwa siswa dengan nama 

_*'.$rowWhatsapp['studentName'].'*_

pada tanggal: '.$formattedDate.'
Keterangan: '.$data['reason'].'. '.$data['comment'].'

_Pesan ini dikirim secara otomatis, silakan menghubungi pihak yang bersangkutan untuk info lebih lanjut_
';
}

function composeTimeGreetingsID()
{
    $now = date('H');
    $greetings = "pagi/siang/sore";

    if($now<11){
        $greetings = "pagi";
    }else if($now<15){
        $greetings = "siang";
    }else if($now<19){
        $greetings = "sore";
    }else {
        $greetings = "malam";
    }
    
    return $greetings;
}