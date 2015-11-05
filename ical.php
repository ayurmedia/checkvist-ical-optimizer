<?php
    // break if no input given.
    $url = "";

    if ( isset( $_GET['url']) ) {
        $url = $_GET['url'];

       # echo $url;
       # echo "<pre>";
        header('Content-type: text/plain; charset=utf-8');
    }


    // validate if url matches checkvist subscription and deny other request.

    // https://checkvist.com/checklists/due.ics?remote_key=***************&all_day_events=false&treat_asap_as_todo=false&include_archived_lists=true&show_not_mine=false

    if ( !preg_match( "/^https\:\/\/checkvist\.com\/checklists\/due\.ics/", $url ) ) {
        ?>
        <h2>ical optimizer for checkvist</h2>

        <p>use format: https://checkvist.com/checklists/due.ics..... </p>
        <p>Times are detected as:</p>
        <ul>
            <li>... @14</li>
            <li>... @14:00 ...</li>
            <li>... @14:00-15:30 ...</li>
            <li>... @13:30 @30m</li>
            <li>... @20:00 @2h </li>
        </ul>
        <p>if there is no @time in the task then time is not modified. </p>
        <form action="?" method="get">
                <label>Enter iCal url from checkvist:</label>
                <input type="text" name="url" value="" />
                <input type="submit" value="send" />
            </form>
        <?php
        exit;
    }

    // load contents of url
    $lines = file( $url );

    // split file into lines

    $lines_count = count( $lines );

    $found_time = false;

    // loop through lines
    foreach( $lines as $line_nr => $line ) {
        $line_out = $line;

        // if line starts with EVENT:BEGIN
        if ( trim($line) == "BEGIN:VEVENT" ) {
            // init time to 0
            $time_start_hour = "00";
            $time_start_min  = "00";
            $time_end_hour   = "00";
            $time_end_min    = "00";
            $found_time = false;

            $index_search = $line_nr +1 ;
            while ( $index_search < $lines_count
                    and
                    !preg_match( "/^END\:VEVENT/", $lines[ $index_search ])   ) {

                $line_test = $lines[$index_search];

                // search in lines to find SUMMARY:
                if (preg_match("/^SUMMARY\:/", $line_test)) {


                    // if found extract start-time, end-time, or duration via regex
                    list($line_key, $line_value) = explode(":", $line_test, 2);

                    // @14:00-15:00         @2h @30m
                    // @(14)(:(00))?(-(15))?(:(00))?        @(2)(h|m)+
                    // @__:__-__:__  , @start-end , @start
                    // @__(d|h|m)    , @duration
                    $nr = '([0-9]{1,2})';

                    // grap start,end
                    $has_matches = preg_match("/\@$nr(\:$nr)?(\-$nr)?(\:$nr)?/", $line_value, $matches);
                    #print_r( $matches );

                    if ($has_matches) {
                        // target start-time
                        if (isset($matches[1])) {
                            $time_start_hour = sprintf("%02d", $matches[1]);
                            $time_end_hour   = $time_start_hour;

                            $found_time = true;
                        }
                        if (isset($matches[3])) {
                            $time_start_min = sprintf("%02d", $matches[3]);
                            $time_end_min = $time_start_min;
                        }

                        // target end-time
                        if (isset($matches[5])) {
                            $time_end_hour = sprintf("%02d", $matches[5]);

                            // invalid interval, negative
                            if( $time_end_hour < $time_start_hour ) {
                                $time_end_hour = $time_start_hour;
                            }
                        }
                        if (isset($matches[7])) {
                            $time_end_min = sprintf("%02d", $matches[7]);

                            // invalid interval, negative
                            if( $time_end_min < $time_start_min ) {
                                $time_end_min = $time_start_min;
                            }
                        }
                    }

                    // grep duration
                    $has_matches = preg_match("/\@([0-9]+)(h|m)/", $line_value, $matches);
                    #print_r( $matches );
                    if ($has_matches) {
                        if (isset($matches[1])) {
                            $time_duration_amount = sprintf("%02d", $matches[1]);
                        }
                        if (isset($matches[2])) {
                            $time_duration_type =  $matches[2] ;
                        }

                        // relative time
                        if ( $time_start_hour.$time_start_min == $time_end_hour.$time_end_min ){
                            if ( $time_duration_type == "h" ) {
                                $time_end_hour = sprintf( "%02d", $time_start_hour + $time_duration_amount ) ;
                            }
                            if ( $time_duration_type == "m" ) {
                             $time_end_min = sprintf( "%02d",$time_start_min + $time_duration_amount );
                            }
                        }
                    }

                    // expand event to 1hour when it is 0 (=undefined)
                    if ( $time_start_hour.$time_start_min == $time_end_hour.$time_end_min ){
                        $time_end_hour = sprintf( "%02d", $time_end_hour + 1);
                    }
                }
                $index_search++;
            }
        }

        // when date-start
        if ( $found_time and preg_match( "/^DTSTART\;/" , $line ) ){
            // extract date-time
            $nr = '([0-9]{6})';
            $has_matches = preg_match( "/([0-9]{8})T([0-9]{6})$/", trim($line) , $matches );
            #print_r( $matches );
            if( $has_matches ) {
                if ( isset( $matches[0] ) ) {
                    $dateTime = DateTime::createFromFormat('Ymd\THis',
                            $matches[1]."T".$time_start_hour . $time_start_min . "00");
                    $new_date_start = $dateTime->format('Ymd\THis' );

                    #$line_out = preg_replace( "/". $matches[0] ."T".  $matches[1] ."/", $new_date_start, $line );
                    $line_out = str_replace(  $matches[0] , $new_date_start, $line );
                }
            }
        }

        if ( $found_time &&  preg_match( "/^DTEND\;/" , $line ) ){
            // extract date-time
            $has_matches = preg_match( "/([0-9]{8})T([0-9]{6})$/", trim($line) , $matches );

            #print_r( $matches );
            if( $has_matches ) {
                if ( isset( $matches[0] ) ) {
                    #echo $matches[1]."T".$time_end_hour . $time_end_min . "00";

                    $dateTime = DateTime::createFromFormat('Ymd\THis',
                            $matches[1]."T".$time_end_hour . $time_end_min . "00");

                    #var_dump( $dateTime);

                    $new_date_end = $dateTime->format('Ymd\THis' );
                    //echo "/". $matches[0] ."T".  $matches[1] ."/";
                    $line_out = str_replace(  $matches[0]  , $new_date_end, $line );
                }
            }
        }

        // Output modified or copied Line
        echo $line_out ; // NL is preserved
    }
    // end loop

