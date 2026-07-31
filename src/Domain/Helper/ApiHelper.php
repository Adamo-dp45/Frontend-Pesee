<?php

namespace App\Domain\Helper;

use App\Domain\Service\ApiClientService;
use App\Security\Exception\ApiException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ApiHelper
{
    public function __construct(
        private readonly ApiClientService $api,
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $params
    )
    {
    }

    public function get(string $endpoint, array $query = [], array $headers = [])
    {
        $response = $this->api->request('GET', $endpoint, [
            'query' => $query,
            'headers' => $headers
        ]);
        if($response->getStatusCode() === 200) {
            $data = $response->toArray(false);
            return $data;
        }
        $this->throwFromResponse($response, $response->getStatusCode());
    }

    public function collection(string $endpoint, array $query = [], array $headers = [])
    {
        $response = $this->api->request('GET', $endpoint, [
            'query' => $query,
            'headers' => $headers
        ]);
        if($response->getStatusCode() === 200) {
            $data = $response->toArray(false);
            return $data['member'] ?? $data['hydra:member'] ?? (array_is_list($data) ? $data : []);
        }
        $this->throwFromResponse($response, $response->getStatusCode());
    }

    public function item(string $endpoint, array $query = [], array $headers = []): ?array
    {
        $response = $this->api->request('GET', $endpoint, [
            'query' => $query,
            'headers' => $headers
        ]);
        if($response->getStatusCode() === 200) {
            return $response->toArray(false);
        }
        $this->throwFromResponse($response, $response->getStatusCode());
    }

    public function post(string $endpoint, array $data = [], array $headers = []): array
    {
        $response = $this->api->request('POST', $endpoint, [
            'json' => $data,
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers)
        ]);

        if($response->getStatusCode() === 204) {
            return [];
        }

        if(in_array($response->getStatusCode(), [200, 201], true)) {
            return $response->toArray(false);
        }
        $this->throwFromResponse($response, $response->getStatusCode());
    }

    public function patch(string $endpoint, array $data = [], array $headers = [])
    {
        $response = $this->api->request('PATCH', $endpoint, [
            'json' => $data,
            'headers' => array_merge(['Content-Type' => 'application/merge-patch+json'], $headers)
        ]);
        $statusCode = $response->getStatusCode();

        if($statusCode === 204) {
            return []; /*
                - 'No Content' succès sans corps
            */
        }

        if($statusCode === 200) {
            return $response->toArray(false);
        }

        $this->throwFromResponse($response, $statusCode);
    }

    public function delete(string $endpoint, array $headers = []): void
    {
        $response = $this->api->request('DELETE', $endpoint, [
            'headers' => $headers
        ]);
        $status = $response->getStatusCode();

        if(in_array($status, [200, 202, 204], true)) {
            return;
        }
        $this->throwFromResponse($response, $status);
    }

    /**
     * Permet de retourner le contenu brut de la réponse, utile pour les pdf ou fichier binaire renvoyer par le serveur
     */
    public function raw(string $endpoint, array $query = [], array $headers = []): array
    {
        $response = $this->api->request('GET', $endpoint, [
            'query' => $query,
            'headers' => array_merge(['Content-Type' => 'application/octet-stream'], $headers)
        ]);
        $status = $response->getStatusCode();

        if(!in_array($status, [200, 201], true)) {
            $this->throwFromResponse($response, $status);
        }

        return [
            'body' => $response->getContent(false),
            'content_type' => $response->getHeaders(false)['content-type'][0] ?? 'application/octet-stream',
            'status' => $status
        ];
    }

    public function postMediaObject(UploadedFile $file)
    {
        $formFields = [
            'file' => new DataPart(
                body: file_get_contents($file->getRealPath()),
                filename: $file->getClientOriginalName(),
                contentType: $file->getMimeType() ?? 'application/octet-stream',
            )
        ];
        $formData = new FormDataPart($formFields);
        $token = $this->api->getToken();

        $headers = $formData->getPreparedHeaders()->toArray();
        $headers[] = 'Accept: application/ld+json';
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $url = $this->params->get('api.endpoint') . '/api/media_objects';

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'body' => $formData->bodyToString()
        ]);

        if(in_array($response->getStatusCode(), [200, 201], true)) {
            return $response->toArray(false);
        }
        $this->throwFromResponse($response, $response->getStatusCode());
    }

    public function multipart(string $endpoint, array $fields = [], array $files = [], string $method = 'POST')
    {
        $formFields = [];

        foreach($fields as $name => $value) {
            $formFields[$name] = json_encode($value);
        }

        foreach($files as $name => $uploadedFile) {
            $formFields[$name] = new DataPart(
                body: file_get_contents($uploadedFile->getRealPath()), /*
                    - Pour avoir le contenu brut et non le chemin
                */
                filename: $uploadedFile->getClientOriginalName(),
                contentType: $uploadedFile->getMimeType() ?? 'application/octet-stream',
            );
        }

        $formData = new FormDataPart($formFields);

        $url = str_starts_with($endpoint, 'http') ? $endpoint : $this->params->get('api.endpoint') . $endpoint;
        $token = $this->api->getToken();

        $headers = $formData->getPreparedHeaders()->toArray();
        $headers[] = 'Accept: application/ld+json';
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $response = $this->httpClient->request($method, $url, [
            'headers' => $headers,
            'body' => $formData->bodyToString() // 'bodyToString()' et non 'bodyToIterable()'
        ]);

        $status = $response->getStatusCode();
        if(in_array($status, [200, 201], true)) {
            return $response->toArray(false);
        }

        $this->throwFromResponse($response, $status);
    }

    private function throwFromResponse(ResponseInterface $response, int $status): never
    {
        $body = $response->toArray(false); /*
            - 'false' ne throw pas même si le status est une erreur
        */
        $message = $body['detail'] ?? $body['description'] ?? $body['title'] ?? "Erreur API ({$status})";

        $violations = [];
        foreach($body['violations'] ?? [] as $v) {
            $field = $v['propertyPath'] ?? 'global';
            $violations[$field][] = $v['message'] ?? 'Erreur inconnue';
        }

        throw new ApiException(
            message: $message,
            code: $status,
            context: [
                'violations' => $violations, /*
                    - Un tableau indexé par champ
                */
                'raw' => $body /*
                    - Le body brut pour debug
                */
            ]
        );
    }

    /* Bilan
     */
    public function getOperations(array $params = []): array
    {
        $query = http_build_query(array_filter($params));
        $url = '/api/bilan' . ($query ? "?$query" : '');

        $response = $this->api->request('GET', $url);
        if($response->getStatusCode() === 200) {
            $data = $response->toArray(false);
            return $data;
        }
        return [];
    }

    public function getReferentiel(string $type, string $code): array
    {
        $response = $this->api->request('POST', '/api/' . $type, [
            'json' => [
                'code' => $code
            ]
        ]);
        if($response->getStatusCode() === 200) {
            $data = $response->toArray(false);
            return $data;
        }
        return [];
    }
}