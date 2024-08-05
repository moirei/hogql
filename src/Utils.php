<?php

namespace MOIREI\HogQl;

use Illuminate\Support\Facades\Http;

class Utils
{
    public static function queryApi(string $query)
    {
        $productId = config('hogql.api_client.product_id');
        $token = config('hogql.api_client.api_token');
        $url = config('hogql.api_client.api_host')."/api/projects/$productId/query";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $token",
        ])->post($url, [
            'query' => [
                'kind' => 'HogQLQuery',
                'query' => $query,
            ],
        ]);

        if ($response->ok()) {
            return $response->json();
        }
    }

    public static function unwrap($str){
        return trim($str, "'\"");
    }
}
