<?php

    function translateDate($date, $format) {

        $TRANSLATOR = new Wonder\Plugin\Custom\Translator\TranslatorDate;

        if ($format == 'day') { return $TRANSLATOR::Day( $date ); }
        if ($format == 'month') { return $TRANSLATOR::Month( $date ); }

    }

    function labelDate($day) {

        $from = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-$day,date('Y')));
        $to = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-1,date('Y')));

        $dates = [];
        
        if ($day >= 2) {

            array_push($dates, $from);

            $date = $from;

            $found = false;

            for($i = 1; $i > 0 && !$found; $i++){

                list($year,$month,$day) = explode("-", $date);
                
                $date = date("Y-m-d",mktime(0,0,0,$month,$day+1,$year));

                if ($date == $to){
                    $found = true;
                } else {
                    array_push($dates, $date);
                }

            }

            array_push($dates, $to);

        }

        if ($day == 1) {
            array_push($dates, $to);
        }

        if ($day == 0) {
            array_push($dates, $from);
        }

        return $dates;

    }

    function arrayDay($from, $to) {

        [$day, $month, $year] = explode("/", $from);
        $from = "$year-$month-$day";
        [$day, $month, $year] = explode("/", $to);
        $to = "$year-$month-$day";

        $dates = array();

        array_push($dates, $from);

        $date = $from;

        $found = false;

        for($i = 1; $i > 0 && !$found; $i++){

            list($year,$month,$day) = explode("-", $date);
            
            $date = date("Y-m-d",mktime(0,0,0,$month,$day+1,$year));

            if($date == $to){
                $found = true;
            }else{
                array_push($dates, $date);
            }

        }

        array_push($dates, $to);

        return $dates;

    }

    function calcYear($initialDate, $finalDate = null) {

        $initialDate = str_replace('/', '-', $initialDate);
        $initialDate = date('Y-m-d', strtotime($initialDate));

        if ($finalDate == null) { 
            $finalDate = date('Y-m-d');
        } else {
            $finalDate = date('Y-m-d', strtotime($finalDate));
        }

        $diff = date_diff(date_create($initialDate), date_create($finalDate));
        $year = $diff->format('%y');

        return $year;

    }

    function prettyTimeTable($timeTable) {

        $RETURN = (object) array();

        $RETURN->timeGroup = [];
        
        foreach ($timeTable as $day => $value) {

            $X = "";
            
            foreach ($value as $key => $hour) { $X .= $hour['from'].'=>'.$hour['to'].'/'; }

            $X = substr($X, 0, -1);
            
            if (!array_key_exists($X, $RETURN->timeGroup)) { $RETURN->timeGroup[$X] = []; }
            
            array_push($RETURN->timeGroup[$X], $day);

        }

        $RETURN->prettyTime = "";
        
        foreach ($timeTable as $day => $value) {

            $RETURN->prettyTime .= "<b>".translateDate($day, 'day').":</b> ";

            foreach ($value as $key => $v) {
                $RETURN->prettyTime .= __t('date.from_to_hours', [
                    'from' => $v['from'],
                    'to' => $v['to']
                ]).'<br>'; 
            }

        }

        $RETURN->prettyTimeGroup = "";
        
        foreach ($RETURN->timeGroup as $hour => $day) {

            foreach ($day as $key => $d) {
                $RETURN->prettyTimeGroup .= '<b>'.substr(translateDate($d, 'day'), 0, 3).'</b>, ';
            }

            $RETURN->prettyTimeGroup = substr($RETURN->prettyTimeGroup, 0, -2).': ';

            $h = explode('/', $hour);

            foreach ($h as $k => $v) {
                $v = explode('=>', $v);
                $RETURN->prettyTimeGroup .= __t('date.from_to_hours', [
                    'from' => $v[0],
                    'to' => $v[1]
                ]).' | '; 
            }

            $RETURN->prettyTimeGroup = substr($RETURN->prettyTimeGroup, 0, -3).'<br>';

        }

        return $RETURN;

    }