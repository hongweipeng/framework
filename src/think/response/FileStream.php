<?php
declare (strict_types = 1);

namespace think\response;

use app\Request;
use think\Container;
use think\Exception;
use think\Response;

/**
 * FileStream Response
 */
class FileStream extends File
{
    protected $request = null;
    protected $chunk_size = 1024 * 1024; // 1M
    protected $first_byte = 0;
    protected $last_byte = 0;
    protected $usleep = 0;

    public function __construct($data = '', int $code = 200, Request $request = null)
    {
        $this->request = $request;
        $this->init($data, $code);
    }

    /**
     * 处理数据
     * @access protected
     * @param  mixed $data 要处理的数据
     * @return mixed
     * @throws \Exception
     */
    protected function output($data)
    {
        return '';
    }

    public function send(): void
    {
        $data = $this->data;
//        ob_clean();
        // 处理文件元信息
        if (!empty($this->name)) {
            $name = $this->name;
        } else {
            $name = pathinfo($data, PATHINFO_BASENAME);
        }
        $mimeType = $this->getMimeType($data);
        $size     = filesize($data);

        // 处理分片信息
        $path = $this->data;
        $this->parseRange($size);

        if ($this->last_byte > 0) {
            $last_byte = $this->first_byte + $this->chunk_size;
            if ($last_byte >= $size) {
                $last_byte = $size - 1;
            }
        } else {
            $last_byte = $size;
        }


        $length = $last_byte - $this->first_byte + 1;
        // 设置响应头部
        $this->header['Accept-Ranges']             = 'bytes';
        $this->header['Content-Type']              = $mimeType ?: 'application/octet-stream';
        $this->header['Content-Disposition']       = 'attachment; filename="' . $name . '"';
        $this->header['Content-Transfer-Encoding'] = 'binary';

        if ($last_byte < $size - 1) {
            $this->code = 206;
            $this->header['Content-Length'] = $length;
            $this->header['Content-Range'] = "bytes {$this->first_byte}-{$last_byte}/{$size}"; // % (first_byte, last_byte, size);
        } else {
            $length = $size;
            $this->header['Content-Length']            = $size;
        }

        $this->lastModified(gmdate('D, d M Y H:i:s', time()) . ' GMT');

        // 发送头部
        if (!headers_sent() && !empty($this->header)) {
            // 发送状态码
            http_response_code($this->code);
            // 发送头部信息
            foreach ($this->header as $name => $val) {
                header($name . (!is_null($val) ? ':' . $val : ''));
            }
        }

        // 发送文件
        $this->sendFile($path, $this->first_byte, $length);
    }

    protected function sendFile(string $path, int $offset, int $length) {
        $chunk_size = $this->chunk_size;
        $fp = fopen($path, 'rb');
        try{
            fseek($fp, $offset);
            $remaining = $length;

            while (!feof($fp) && $remaining > 0) {
                flush();
                $bytes_length = min($chunk_size, $length);
                $data = fread($fp, $bytes_length);
                $remaining -= $bytes_length;
                echo $data;
                ob_flush();
                sleep(1);
                if ($this->usleep > 0) {
                    usleep($this->usleep);
                }
            }
        } finally {
            fclose($fp);
        }
        ob_end_flush();
    }
    
    protected function parseRange(int $file_size) {
        $re = '/bytes\s*=?\s*(\d+)\s*-\s*(\d*)/';
        $range_header = $this->request->header('range', '');
        preg_match($re, $range_header, $matchs);

        $this->first_byte = isset($matchs[1]) ? (int) $matchs[1] : 0;
        $this->last_byte  = isset($matchs[2]) ? (int) $matchs[2] : 0;
    }
    

    public static function create($data = '', string $type = 'filestream', int $code = 200): Response
    {
        return Container::getInstance()->invokeClass(self::class, [$data, $code]);
    }

    public function chunk(int $chunk)
    {
        $this->chunk_size = $chunk;
        return $this;
    }

    public function usleep(int $usleep)
    {
        $this->usleep = $usleep;
        return $this;
    }

}
