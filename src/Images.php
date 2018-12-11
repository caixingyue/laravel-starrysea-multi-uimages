<?php

namespace Starrysea\Uimages;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Starrysea\Apis\Apis;
use Starrysea\Arrays\Arrays;

abstract class Images extends Controller
{
    /**
     * 是否开启隐秘模式
     * @var bool
     */
    protected $secret = false;

    /**
     * 构造程序
     */
    function __construct()
    {
        $this->allow_domain_post();
    }

    /**
     * 上传程序
     * @return object
     */
    public function upload(Request $request)
    {
        $filed  = $request->file('file');
        $base64 = $request->post('file');

        $rudata = [];
        $errsum = 0;

        if ($filed){
            $filed = Arrays::toArray($filed);
            $data  = $this->uploadFile($filed);
            $rudata = array_merge_recursive($rudata, $data[0]);
            $errsum += $data[1];
        } if ($base64){
            $base64 = Arrays::toArray($base64);
            $data   = $this->uploadBase64($base64);
            $rudata = array_merge_recursive($rudata, $data[0]);
            $errsum += $data[1];
        }

        $zupcount = count($rudata);
        if ($errsum === 0){
            return Apis::first()->success('上传成功')->getJson();
        }elseif ($errsum > 0 && $zupcount > 0){
            return Apis::first()->success('上传成功，其中' . $errsum . '张因编码或格式问题上传失败')->getJson();
        }else{
            return Apis::first()->error('上传失败，请刷新重试')->getJson(422);
        }
    }

    /**
     * 输出隐秘图片流
     * @param string $file 隐秘图片流数据
     */
    public function getPicture($file)
    {
        // base64解密
        $file = base64_decode($file);

        // 获取真实路径
        $file = storage_path('app/secret/' . $file);

        // 验证图片是否存在
        if(!file_exists($file)){ abort(404); }

        // 获取图片格式
        $type = substr(strrchr($file, '.'), 1);

        // 输出图片流
        header('Content-type: image/' . $type);
        print file_get_contents($file);
    }

    /**
     * 储存file文件域图片
     * @param array $filed 文件域数据
     * @return array
     */
    private function uploadFile(array $filed)
    {
        $rudata = [];
        $errsum = 0;

        foreach ($filed as $value){
            if ($value->isValid()){ // 验证数据是否正常

                // 验证授权格式
                $format = $value->extension();
                if (!$this->vAccept($format)){ $this->call_error('图片格式不在授权范围内', $format); $errsum++; continue; }

                // 获取储存路径
                $path = $this->getPath(false);

                // 生成文件
                $path = $value->store($path);

                // 转换路径为完整路径
                $path = storage_path('app/' . $path);

                // 获取域名访问地址
                $url = $this->getUrl($path);
                array_push($rudata, $url);

                // 通知上传成功
                $this->call_success($value, $url, $path);
            }else{
                $errsum++;

                // 通知上传失败
                $this->call_error('file 文件错误', $value);
            }
        }

        return [$rudata, $errsum];
    }

    /**
     * 储存base64数据图片
     * @param array $filed base64数据
     * @return array
     */
    private function uploadBase64(array $filed)
    {
        $rudata = [];
        $errsum = 0;

        foreach($filed as $value){
            // 验证数据是否正常
            if (!strpos($value, 'base64,')){ $this->call_error('base64 数据错误', $value); $errsum++; continue; }

            // 获取图片信息
            list($type, $data) = explode(',', $value);

            // 验证数据是否正常
            if (!$data){ $this->call_error('base64 数据错误', $value); $errsum++; continue; }

            // 获取文件后缀
            $format = $this->getBase64Type($type);
            if (!$format){ $errsum++; continue; }

            // 验证授权格式
            if (!$this->vAccept($format)){ $this->call_error('图片格式不在授权范围内', $format); $errsum++; continue; }

            // 获取储存路径
            $path = $this->getPath();

            // 生成图片地址
            $random = md5(uniqid('',true) . rand(1, 999999));
            $path = $path . '/' . $random . '.' . $format;

            // 生成文件
            file_put_contents($path, base64_decode($data), true);

            // 验证是否储存成功
            if(!file_exists($path)){ $this->call_error('图片储存失败', $value); $errsum++; continue; }

            // 获取域名路径
            $url = $this->getUrl($path);
            array_push($rudata, $url);

            // 通知上传成功
            $this->call_success($value, $url, $path);
        }

        return [$rudata, $errsum];
    }

    /**
     * 获取并建立年月日储存目录
     * @param bool $complete 是否获取完整路径
     * @return string
     */
    private function getPath(bool $complete = true)
    {
        // 配置文件储存目录
        $storage = $this->storage();
        $stodate = date('Ymd', time());

        if ($this->secret === true){
            $file = '/secret/images/' . $storage . '/' . $stodate; // 文件储存在非公开目录
        }else{
            $file = '/public/images/' . $storage . '/' . $stodate; // 文件储存在公开目录
        }

        // 转换路径双斜杠及三斜杆为单斜杆
        $file = str_replace('///', '/', $file);
        $file = str_replace('//', '/', $file);

        // 创建目录[已存在不创建]
        if(!file_exists(storage_path('app' . $file)))
            mkdir(storage_path('app' . $file), 0777, true);

        // 得到储存目录路径
        return $complete ? storage_path('app' . $file) : $file;
    }

    /**
     * 路径地址转访问地址
     * @param string $path 文件路径
     * @return string
     */
    private function getUrl(string $path)
    {
        $url = storage_path('app/');
        $url = str_replace($url, '', $path);

        if ($this->secret === true){
            $url = str_replace('secret/', '', $url);
            $url = base64_encode($url);
            $url = $this->secretUrl() . '/' . $url;
            $url = str_replace('//', '/', $url);
        }else{
            $url = str_replace('public', 'storage', $url);
            $url = asset($url);
        }

        return $url;
    }

    /**
     * 获取base64数据的图片文件类型
     * @param string $base64 base64短数据
     * @return array|bool|int|string
     */
    private function getBase64Type($base64)
    {
        $dara = explode(':', $base64);
        $dara = explode(';', $dara[1]);
        $dara = explode('/', $dara[0]);
        if ($dara[0] === 'image'){
            return $dara[1];
        }else{
            $this->call_error('base64 数据错误', $base64);
            return false;
        }
    }

    /**
     * 验证图片授权
     * @param string $format 格式
     * @return bool
     */
    private function vAccept($format)
    {
        $accept = $this->accept();
        $accept = is_array($accept) ? $accept : explode('|', $accept);
        return in_array($format, $accept);
    }

    /**
     * 配置跨域请求白名单
     * @return bool
     * @throws \Exception
     */
    private function allow_domain_post()
    {
        $domain = $this->crossDomainWhitelist();
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

        header('content-type:application/json;charset=utf-8');
        header('Access-Control-Allow-Methods:POST');
        header('Access-Control-Allow-Headers:x-requested-with,content-type');

        if ($domain === '*'){
            header('Access-Control-Allow-Origin:*'); // 允许所有域名请求
        }elseif (in_array($origin, (array) $domain)){
            header('Access-Control-Allow-Origin:' . $origin); // 仅允许指定域名请求
        }else{
            throw new \Exception(json_encode(Apis::first()->error('您没有权限上传图片')->getJson()->getData()));
        }

        return true;
    }

    /**
     * 跨域白名单, 允许所有域名用 “*” 号
     * @return string|array
     */
    protected function crossDomainWhitelist()
    {
        return [
            //
        ];
    }

    /**
     * 可接受的图片格式
     * @return string|array
     */
    protected function accept()
    {
        return 'jpeg|gif|png|bmp';
    }

    /**
     * 隐秘图片访问地址
     * @return string
     */
    protected function secretUrl()
    {
        return URL::full();
    }

    /**
     * 图片储存目录
     * @return string
     */
    protected function storage()
    {
        return '/';
    }

    /**
     * 成功回调
     * @param string|array|object $filed 原始数据
     * @param string $url 访问地址
     * @param string $path 真实路径
     */
    protected function call_success($filed, string $url, string $path){}

    /**
     * 失败回调
     * @param string $message 失败原因
     * @param string|array|bool|int|object $data 数据
     */
    protected function call_error(string $message, $data = ''){}
}