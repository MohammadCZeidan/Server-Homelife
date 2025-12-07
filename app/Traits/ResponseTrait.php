<?php

namespace App\Traits;

trait ResponseTrait
{
    protected function responseJSON($payload, $status = "success", $status_code = 200, $message = null)
    {
        $response = [
            "status" => $status,
            "payload" => $payload
        ];
        
        if ($message !== null) {
            $response["message"] = $message;
        }
        
        return response()->json($response, $status_code);
    }
}

