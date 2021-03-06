<?php
//$SOURCE_LICENSE$

/*<requires>*/
//GearLogger
//GearHttpHelper
/*</requires>*/

/*<namespace.current>*/
namespace gear\arch\http;
/*</namespace.current>*/
/*<namespace.use>*/
use gear\arch\app\GearAppEngine;
use gear\arch\GearLogger;
use gear\arch\helpers\GearHttpHelper;
/*</namespace.use>*/

/*<bundles>*/
/*</bundles>*/

/*<module>*/
class GearHttpClient
{
    private
        $url,
        $post,
        $headers,
        $body,
        $requestType;
    
    private $requestExcludedHeaders = [];
    private $responseExcludedHeaders = [];
    
    public $hasReturn = true;
    public $hasReturnHeaders = true;
    public $useSsl = false;
    
    public function __construct(
        $url,
        $body,
        $headers,
        $requestType
    )
    {
        $this->url = $url;
        $this->body = $body;
        $this->headers = $headers;
        $this->requestType = $requestType;
    }

    /**
     * Create a GearHttpClient from GearHttpRequest.
     *
     * @param string $url
     * @param IGearHttpRequest $request
     * @return string
     */
    public static function fromRequest($url, $request)
    {
        return new self(
            $url,
            $request->getBody(),
            $request->getHeaders(),
            $request->getMethod()
        );
    }

    /**
     * Excludes a header from request.
     *
     * @param string $key
     * @return string
     */
    public function excludeRequestHeader($key)
    {
        $this->requestExcludedHeaders[] = $key;
    }
    
    /**
     * Excludes a header from response.
     *
     * @return string
     */
    public function excludeResponseHeader($key)
    {
        $this->responseExcludedHeaders[] = $key;
    }

    /**
     * Add/replace a header to request.
     *
     * @param string $key
     * @param mixed $value
     * @return string
     */
    public function addHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }

    /**
     * Execute curl request.
     *
     * @return string
     * @throws \Exception
     */
    public function execute()
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $this->url);
        //curl_setopt($ch, CURLOPT_POST,  $this->post);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->requestType);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, $this->hasReturn);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
        curl_setopt($ch, CURLOPT_HEADER, $this->hasReturnHeaders);
        
        if (GearAppEngine::isDebug()) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }
        
        $requestHeaders = $this->headers;
        
        if ($requestHeaders != null && sizeof($requestHeaders) > 0) {
            $requestHeaders = array_diff_ukey($requestHeaders, array_flip($this->requestExcludedHeaders), 'strcasecmp');
        }
        
        $curlHeaders = [];
        foreach ($requestHeaders as $key => $value) {
            $curlHeaders[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        
        if ($this->useSsl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        
        $response = curl_exec($ch);    
        //$info = curl_getinfo($ch);
        //\GearLogger::write($info);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === FALSE)
        {
            if (GearAppEngine::isDebug()) {
                GearLogger::write($error);
            }
            throw new \Exception($error);
        }
        
        $responseBody = substr($response, $header_size);
        $responseHeaders = $this->hasReturnHeaders ? substr($response, 0, $header_size) : null;
        
        if (GearAppEngine::isDebug()) {
            GearLogger::write('curl successfull request on '.$this->url);
        }
        
        return [
            'body' => $responseBody,
            'headers' => $responseHeaders
        ];
    }
    
    /**
     * Execute curl request and map result to current response.
     *
     * @return string
     */
    public function executeResponse()
    {
        $result = $this->execute();
        
        $body = $result['body'];
        $rawHeaders = $result['headers'];
        
        $headers = GearHttpHelper::parseHeaders($rawHeaders);
        $headers = array_diff_ukey($headers, array_flip($this->responseExcludedHeaders), 'strcasecmp');
        
        if ($headers != null) {
            foreach ($headers as $key => $value) {
                foreach ($value as $val) {
                    header("$key: $val");
                }
            }
        }

        //! Do not remove.
        echo $body;
        
        return $result;
    }
}
/*</module>*/
?>