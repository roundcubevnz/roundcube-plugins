<?php


class ScriptManager
{
    private $scriptCode;

    # Sections for the script building
    private $requires = array('"date"','"relational"','"vacation"');

    public function BuildScriptFromParams($params)
    {
        # Read variables
        $message = $params['message'];

        # Detect the format
        $format = 'text';
        if ( strpos('<html', $message) ) $format = 'html';
        
        # Build vacation scripts
        $execBlock =
            sprintf("## Generated by Vacation Sieve plugin for roundcube, the %s ##\n\n", date('r'));

        # Include initial params in the script
        $execBlock .= sprintf("## Initial params: ##\n##STARTPARAMS%sENDPARAMS\n\n", json_encode($params));

        if($params['enable'])
        {
            # Add require blocks
            if ( $params['appendSubject'] ) $this->requires[] = '"variables"';
            $execBlock .= sprintf('require [%s];'."\n\n", join(",",$this->requires));

            # Add needed variables
            if ( $params['appendSubject'] )
            {
                $execBlock .= 'set "subject" "";';
                $execBlock .= 'if header :matches "subject" "*" { set "subject" "${1}"; }' ;
                $execBlock .= "\n\n";
            }

            # Build conditions
            $startDate = preg_replace('#(\d\d)/(\d\d)/(\d\d\d\d)#','$3-$2-$1',$params['start']);
            $endDate = preg_replace('#(\d\d)/(\d\d)/(\d\d\d\d)#','$3-$2-$1',$params['end']);

            if ( empty($params['starttime']) && empty($params['endtime']) )
            {
                # Different days, hours not specified
                $execBlock .= sprintf('if allof (currentdate :value "ge" "date" "%s",'.
                                               ' currentdate :value "le" "date" "%s")'."\n",
                    $startDate,
                    $endDate);
            }
            elseif ( $startDate == $endDate )
            {
                # Same day, between two hours
                $execBlock .= sprintf(
                    'if allof (currentdate :value "eq" "date" "%s", currentdate :value "ge" "hour" "%02d", '.
                             ' currentdate :value "le" "hour" "%02d")',
                    $startDate,
                    $params['starttime'],
                    $params['endtime']);
            }
            else
            {
                # With Hour Selection
                # - day is first day and current hour > starttime
                # - day is last day and current hour < endtime
                # - current day is > startdate and current day is < enddate
                if ( empty($params['starttime']) )
                    $params['starttime'] = '00:00';
                if ( empty($params['endtime']) )
                    $params['starttime'] = '23';

                $execBlock .= sprintf(
                    'if anyof ( allof (currentdate :value "eq" "date" "%s", currentdate :value "ge" "hour" "%02d"),'.
                              ' allof (currentdate :value "eq" "date" "%s", currentdate :value "le" "hour" "%02d"),'.
                              ' allof (currentdate :value "gt" "date" "%s", currentdate :value "lt" "date" "%s")'.
                              ')',
                    $startDate,
                    $params['starttime'],
                    $endDate,
                    $params['endtime'],
                    $startDate,
                    $endDate);
            }

            # Start to build the script
            $execBlock .= "\n{\n    vacation\n";

            $execBlock .= sprintf("        :days %d\n", $params['every']);
            
            # Add addresses
            if ( is_array($params['addresses']) )
            {
                $addresses = array();
                foreach ( $params['addresses'] as $address )
                {
                    $address = preg_replace('/.*<(.*)>/', '"$1"', $address);
                    $addresses[] = $address;
                }
                $execBlock .= sprintf("        :addresses [%s]\n", join(",",$addresses));
            }

            # Set subject
            $subject = str_replace('"', '\\"', $params['subject']);
            if ( $params['appendSubject'] )
                $execBlock .= sprintf('        :subject "%s: ${subject}"'."\n", $subject);
            else
                $execBlock .= sprintf('        :subject "%s"'."\n", $subject);

            # This regenerate a different handler every time the filter is saved.
            $handle = substr(md5(mt_rand()),0,8);
            $execBlock .= sprintf('        :handle "%s"'."\n", $handle);

            # Add the from address
            $sendFrom = preg_replace('/.*<(.*)>/', '$1', $params['sendFrom']);
            $execBlock .= sprintf('        :from "%s"'."\n", $sendFrom);
            
            # Add the message in text format
            $message = str_replace('"', '\\"', $params['message']);
            $message = trim($message);
            $execBlock .= sprintf('        "%s";'."\n", $message);
            
            $execBlock .= "}";
        }

        #header('Content-type: text/plain');
        #print($execBlock);
        #exit;

        return $execBlock;
    }

    public function LoadParamsFromScript($script)
    {
        $params = json_decode($script, true);
        return $params;
    }


}
