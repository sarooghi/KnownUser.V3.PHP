<?php 
namespace QueueIT\KnownUserV3\SDK;
class QueueUrlParams
    {
        const TimeStampKey = "ts";
        const ExtendableCookieKey = "ce";
        const CookieValidityMinuteKey = "cv";
        const HashKey = "h";
        const EventIdKey = "e";
        const KeyValueSeparatorChar = '_';
        const KeyValueSeparatorGroupChar = '~';

        public $timeStamp;
        public $eventId;
        public $hashCode;
        public $extendableCookie;
        public $cookieValidityMinute;
        public $queueITToken;
        public $queueITTokenWithoutHash;
        public static function extractQueueParams(string $queueitToken):QueueUrlParams
        {

            $result =  new QueueUrlParams();
            $result->queueITToken= $queueitToken;
            $paramsNameValueList = explode(QueueUrlParams::KeyValueSeparatorGroupChar,$result->queueITToken );
           
            foreach($paramsNameValueList as $pNameValue)
            {
                 $paramNameValueArr = explode(QueueUrlParams::KeyValueSeparatorChar,$pNameValue );
 
                 switch($paramNameValueArr[0])
                 {
                     case QueueUrlParams::TimeStampKey:
                     {
                         if(is_numeric($paramNameValueArr[1]) )
                            $result->timeStamp = intval($paramNameValueArr[1]);
                        else
                            $result->timeStamp = 0;
                        break;
                     }
                     case QueueUrlParams::CookieValidityMinuteKey:
                     {
                         if(is_numeric($paramNameValueArr[1]) )
                            $result->cookieValidityMinute = intval($paramNameValueArr[1]);
                        break;
                     }
                     case QueueUrlParams::EventIdKey:
                     {
                        $result->eventId = $paramNameValueArr[1];
                        break;
                     }
                     case QueueUrlParams::ExtendableCookieKey:
                     {
                            $result->extendableCookie = $paramNameValueArr[1]==='True' || $paramNameValueArr[1]==='true' ;
                            break;
                    }
                    case QueueUrlParams::HashKey:
                     {
                            $result->hashCode = $paramNameValueArr[1];
                            break;
                    }
                 }
            }

                $result->queueITTokenWithoutHash = str_replace(
                    QueueUrlParams::KeyValueSeparatorGroupChar 
                    .QueueUrlParams::HashKey
                    .QueueUrlParams::KeyValueSeparatorChar
                    .(isset($result->hashCode)?$result->hashCode:""), "", $result->queueITToken);
  
            return $result;

        }
  
    }