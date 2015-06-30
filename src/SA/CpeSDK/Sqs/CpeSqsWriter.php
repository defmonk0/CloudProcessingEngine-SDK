<?php

/**
 * This class is used by the CPE Core ActivityPoller
 * AND can be also used by the Custom Activities
 *
 * Use it to send updates to the Clients.
 **/

namespace SA\CpeSdk\Sqs;

// Amazon libraries
use Aws\Common\Aws;
use Aws\Sqs;

// SA Cpe SDK
use SA\CpeSdk;

class CpeSqsWriter
{
    private $sqs;
    private $logger;

    // Exceptions
    const INVALID_JSON       = "INVALID_JSON";
    
    function __construct($debug)
    {
        $this->debug  = $debug;

        // Create AWS SDK instance
        $aws = Aws::factory(array(
                'region' => getenv("AWS_DEFAULT_REGION")
            ));
        $this->sqs = $aws->get('Sqs');

        // Logger
        $this->logger = new CpeLogger();
    }

    
    /**
     * SEND messages to OUTPUT SQS queue
     *
     * Send messages to back to the clients.
     * Clients listening to the SQS queue will receive them
     * Send updates and notifications:
     * Activity Started, Failed, Succeeded, etc
     */
    
    public function activity_started($task)
    {
        // last param to 'true' to force sending 'input' info back to client
        $this->send_activity_msg(
            $task, 
            self::ACTIVITY_STARTED,
            true
        );
    }

    public function activity_completed($task, $result = null)
    {
        // Last param append extra data to the message to send back to the client. In this case the result data.
        $this->send_activity_msg(
            $task, 
            self::ACTIVITY_COMPLETED,
            false,
            $result
        );
    }

    public function activity_failed($task, $reason, $details)
    {
        $this->send_activity_msg(
            $task, 
            self::ACTIVITY_FAILED,
            false,
            [
                "reason"  => $reason,
                "details" => $details
            ]
        );
    }

    public function activity_timeout()
    {
        $this->send_activity_msg(
            $task, 
            self::ACTIVITY_TIMEOUT,
            true
        );
    }

    public function activity_canceled()
    {
    }

    public function activity_progress($task, $progress)
    {
        $this->send_activity_msg(
            $task, 
            self::ACTIVITY_PROGRESS, 
            false,
            $progress
        );
    }

    public function activity_preparing($task)
    {
        $this->send_activity_msg(
            $task, 
            self::ACTIVITY_PREPARING
        );
    }

    public function activity_finishing($task)
    {
        $this->send_activity_msg(
            $task, 
            self::ACTIVITY_FINISHING
        );
    }

    
    /**
     * UTILS
     */

    // Craft a new message array
    private function craft_new_msg($type, $jobId, $data)
    {
        $msg = array(
            'time'   => microtime(true),
            'type'   => $type,
            'job_id' => $jobId,
            'data'   => $data
        );

        return $msg;
    }

    // Send a message to SQS output queue
    private function send_activity_msg(
        $activityTask, 
        $eventType, 
        $sendInput = false, 
        $extra = false)
    {
        if (!($input = json_decode($activityTask->get('input'))))
            throw new CpeException("Task input JSON is invalid!\n".$activityTask->get('input'),
                INVALID_JSON);
        
        $activity = [
            'activityId'   => $activityTask->get('activityId'),
            'activityType' => $activityTask->get('activityType')
        ];
        
        // Want to send back the input data ?
        if ($sendInput)
            $activity['input'] = $input->{"data"};
        
        // Extra data? Concat to data array.
        if ($extra && is_array($extra) && count($extra))
            foreach ($extra as $key => $value)
                $activity[$key] = $value;
        
        // Initial data structure
        $data = array(
            'workflow' => $activityTask->get('workflowExecution'),
            'activity' => $activity
        );
        
        $msg = $this->craft_new_msg(
            $eventType,
            $input->{"job_id"},
            $data
        );

        // Send message to SQS output queue
        $client = $input->{"client"};
        $this->sqs->sendMessage(array(
                'QueueUrl'    => $client->{'queues'}->{'output'},
                'MessageBody' => json_encode($msg),
            ));
    }
}