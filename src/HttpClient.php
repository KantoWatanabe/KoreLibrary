<?php
/**
 * Kore : Simple And Minimal Framework
 *
 */

namespace Kore;

use Kore\Log;

/**
 * HttpClient class
 *
 */
class HttpClient
{
    /**
     * connect timeout
     *
     * Used as the value of CURLOPT_CONNECTTIMEOUT.
     * If unspecified, the default is 300.
     * For unlimited, specify 0.
     * @var int
     */
    protected $connectTimeout = 300;
    /**
     * timeout
     *
     * Used as the value of CURLOPT_TIMEOUT.
     * If unspecified, the default is 300.
     * For unlimited, specify 0.
     * @var int
     */
    protected $timeout = 300;

    /**
     * Set connect timeout
     *
     * @param int $connectTimeout connect timeout
     * @return void
     */
    public function setConnectTimeout($connectTimeout)
    {
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * Set timeout
     *
     * @param int $timeout timeout
     * @return void
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }
    
    /**
     * GET Communication
     *
     * @param string $url request url
     * @param array<mixed> $params request parameters
     * @param array<mixed> $headers request headers
     * @param string|null $userpwd user name and password
     * @return HttpResponse \Kore\HttpResponse
     * @throws \Exception
     * @see \Kore\HttpResponse
     */
    public function get($url, $params = array(), $headers = array(), $userpwd = null)
    {
        return $this->communicate('GET', $url, $params, $headers, $userpwd);
    }

    /**
     * POST Communication
     *
     * @param string $url request url
     * @param array<mixed> $params request parameters
     * @param array<mixed> $headers request headers
     * @param string|null $userpwd user name and password
     * @return HttpResponse \Kore\HttpResponse
     * @throws \Exception
     * @see \Kore\HttpResponse
     */
    public function post($url, $params = array(), $headers = array(), $userpwd = null)
    {
        return $this->communicate('POST', $url, $params, $headers, $userpwd);
    }

    /**
     * PUT Communication
     *
     * @param string $url request url
     * @param array<mixed> $params request parameters
     * @param array<mixed> $headers request headers
     * @param string|null $userpwd user name and password
     * @return HttpResponse \Kore\HttpResponse
     * @throws \Exception
     * @see \Kore\HttpResponse
     */
    public function put($url, $params = array(), $headers = array(), $userpwd = null)
    {
        return $this->communicate('PUT', $url, $params, $headers, $userpwd);
    }

    /**
     * PATCH Communication
     *
     * @param string $url request url
     * @param array<mixed> $params request parameters
     * @param array<mixed> $headers request headers
     * @param string|null $userpwd user name and password
     * @return HttpResponse \Kore\HttpResponse
     * @throws \Exception
     * @see \Kore\HttpResponse
     */
    public function patch($url, $params = array(), $headers = array(), $userpwd = null)
    {
        return $this->communicate('PATCH', $url, $params, $headers, $userpwd);
    }

    /**
     * DELETE Communication
     *
     * @param string $url request url
     * @param array<mixed> $params request parameters
     * @param array<mixed> $headers request headers
     * @param string|null $userpwd user name and password
     * @return HttpResponse \Kore\HttpResponse
     * @throws \Exception
     * @see \Kore\HttpResponse
     */
    public function delete($url, $params = array(), $headers = array(), $userpwd = null)
    {
        return $this->communicate('DELETE', $url, $params, $headers, $userpwd);
    }

    /**
     * Communication Processing
     *
     * @param string $method http method
     * @param string $url request url
     * @param array<mixed> $params request parameters
     * @param array<mixed> $headers request headers
     * @param string|null $userpwd user name and password
     * @return HttpResponse \Kore\HttpResponse
     * @throws \Exception
     * @see \Kore\HttpResponse
     */
    protected function communicate($method, $url, $params = array(), $headers = array(), $userpwd = null)
    {
        $headers = $this->buildHeader($headers);
        
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        if ($method === 'GET') {
            $url = url_add_query($url, $params);
            curl_setopt($curl, CURLOPT_URL, $url);
        } elseif ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
            curl_setopt($curl, CURLOPT_URL, $url);
            $json_headers = preg_grep("/^Content-Type: application\/json/i", $headers);
            if ($json_headers !== false && count($json_headers) > 0) {
                $data = json_encode($params);
            } else {
                $data = http_build_query($params);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        if ($userpwd !== null) {
            curl_setopt($curl, CURLOPT_USERPWD, $userpwd);
        }

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $total_time = curl_getinfo($curl, CURLINFO_TOTAL_TIME);

        curl_close($curl);

        Log::debug(sprintf('[%s][%s][%ssec]', $url, $http_code, $total_time));
        if ($response === false || !is_string($response)) {
            throw new \Exception("Communication Error[$url($http_code)]");
        }
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        return new HttpResponse($http_code, $header, $body);
    }

    /**
     * Build headers
     *
     * Build the associative array headers into a curl-friendly format.
     * @param array<mixed> $headers headers
     * @return array<string> built headers
     */
    protected function buildHeader($headers)
    {
        $h = array();
        foreach ($headers as $key => $value) {
            if (is_string($key)) {
                $h[] = "$key: $value";
            } else {
                $h[] = $value;
            }
        }
        return $h;
    }
}

/**
 * HttpResponse class
 *
 */
class HttpResponse
{
    /**
     * __construct method
     *
     * @param int $httpCode http status code
     * @param string $header response headers
     * @param string $body response body
     * @return void
     */
    public function __construct($httpCode, $header, $body)
    {
        $this->httpCode = $httpCode;
        $this->header = $header;
        $this->body = $body;
    }

    /**
     * http status code
     *
     * @var int
     */
    private $httpCode;
    /**
     * response headers
     *
     * @var string
     */
    private $header;
    /**
     * response body
     *
     * @var string
     */
    private $body;

    /**
     * Get the http status code
     *
     * @return int http status code
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * Get the response headers
     *
     * @return string response headers
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * Get the header value by specifying the header name
     *
     * @param string $key header name
     * @return string|null header value
     */
    public function getHeaderLine($key)
    {
        preg_match("/$key: (\S*)/i", $this->getHeader(), $matches);
        if (!isset($matches[1])) {
            return null;
        }
        return $matches[1];
    }

    /**
     * Get the response body
     *
     * @return string response body
     */
    public function getBody()
    {
        return $this->body;
    }
    
    /**
     * Get the response body in json format
     *
     * @return array<mixed> response body
     */
    public function getJsonBody()
    {
        return json_decode($this->getBody(), true);
    }
}
