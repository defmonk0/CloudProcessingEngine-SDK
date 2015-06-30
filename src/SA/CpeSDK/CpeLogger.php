<?php

namespace SA\CpeSdk;

// SA Cpe SDK
use SA\CpeSdk;

/**
 * Allow formatted logging on STDOUT
 * Send logs to Syslog for log offloading
 */
class CpeLogger
{
    public $logPath;

    // Exception
    const LOG_TYPE_ERROR = "LOG_TYPE_ERROR";
    const OPENLOG_ERROR  = "OPENLOG_ERROR";
    
    public function __construct($logPath = null)
    {
        global $argv;
                
        if (!$logPath)
            $this->logPath =
                "/var/log/cpe/".$argv[0].".log";
        else
            $this->logPath = $logPath;
    }

    // Log message to syslog and log file
    function log_out(
        $type,
        $source,
        $message,
        $workflowId = null)
    {
        global $argv;
    
        $log = [
            "time"    => time(),
            "source"  => $source,
            "type"    => $type,
            "message" => $message
        ];
    
        if ($workflowId)
            $log["workflowId"] = $workflowId;

        // Open Syslog. Use programe name as key
        if (!openlog ($argv[0], LOG_CONS|LOG_PID, LOG_LOCAL1))
            throw new CpeException("Unable to connect to Syslog!",
                OPENLOG_ERROR);

        // Change Syslog priority level
        switch ($type)
        {
        case "INFO":
            $priority = LOG_INFO;
            break;
        case "ERROR":
            $priority = LOG_ERR;
            break;
        case "FATAL":
            $priority = LOG_ALERT;
            break;
        case "WARNING":
            $priority = LOG_WARNING;
            break;
        case "DEBUG":
            $priority = LOG_DEBUG;
            break;
        default:
            throw new CpeException("Unknown log Type!", 
                LOG_TYPE_ERROR);
        }

        // Print log in file
        $this->print_to_file($log, $workflowId);
        
        // Encode log message in JSON for better parsing
        $out = json_encode($log);
        // Send to syslog
        syslog($priority, $out);
    }

    // Write log in file
    private function print_to_file($log, $workflowId)
    {
        if (!is_string($log['message']))
            $log['message'] = json_encode($log['message']);
        
        $toPrint = $log['time'] . " [" . $log['type'] . "] [" . $log['source'] . "] ";
        // If there is a workflow ID. We append it.
        if ($workflowId)
            $toPrint .= "[$workflowId] ";
        $toPrint .= $log['message'] . "\n";
            
        if (file_put_contents(
                $this->logPath,
                $toPrint,
                FILE_APPEND) === false)
            print "ERROR: Can't write into log file!\n";
    }
}