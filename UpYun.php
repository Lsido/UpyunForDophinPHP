<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DolphinPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2017 河源市卓锐科技有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://dolphinphp.com
// +----------------------------------------------------------------------
// | 开源协议 ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------

namespace plugins\UpYun;

require_once ROOT_PATH.'plugins/UpYun/SDK/vendor/autoload.php';

use app\common\controller\Plugin;
use app\admin\model\Attachment as AttachmentModel;
use think\Db;
use Upyun\Upyun as UpyunSdk;
use Upyun\Config;

class UpYun extends Plugin
{
    /**
     * @var array 插件信息
     */
    public $info = [
        // 插件名[必填]
        'name'        => 'UpYun',
        // 插件标题[必填]
        'title'       => '又拍云上传插件',
        // 插件唯一标识[必填],格式：插件名.开发者标识.plugin
        'identifier'  => 'up_yun.lsido.plugin',
        // 插件图标[选填]
        'icon'        => 'fa fa-fw fa-upload',
        // 插件描述[选填]
        'description' => '仅支持DolphinPHP1.0.6以上版本，安装后，需将【<a href="/admin.php/admin/system/index/group/upload.html">上传驱动</a>】将其设置为“又拍云”。在附件管理中删除文件，并不会同时删除又拍云上的文件。',
        // 插件作者[必填]
        'author'      => 'lsido',
        // 作者主页[选填]
        'author_url'  => 'https://lsido.com',
        // 插件版本[必填],格式采用三段式：主版本号.次版本号.修订版本号
        'version'     => '1.0.0',
        // 是否有后台管理功能[选填]
        'admin'       => '0',
    ];

    /**
     * @var array 插件钩子
     */
    public $hooks = [
        'upload_attachment'
    ];

    /**
     * 上传附件
     * @param string $file 文件对象
     * @param array $params 参数
     * @author lsido <502354863@qq.com>
     * @return mixed
     */
    public function uploadAttachment($file = '', $params = [])
    {
        $config = $this->getConfigValue();

        $error_msg = '';
        if ($config['ak'] == '') {
            $error_msg = '未填写【操作用户】';
        } elseif ($config['sk'] == '') {
            $error_msg = '未填写【操作密码】';
        } elseif ($config['bucket'] == '') {
            $error_msg = '未填写【服务名】';
        } elseif ($config['domain'] == '') {
            $error_msg = '未填写【绑定域名】';
        }
        if ($error_msg != '') {
            switch ($params['from']) {
                case 'wangeditor':
                    return "error|{$error_msg}";
                    break;
                case 'ueditor':
                    return json(['state' => $error_msg]);
                    break;
                case 'editormd':
                    return json(["success" => 0, "message" => $error_msg]);
                    break;
                case 'ckeditor':
                    return ck_js(request()->get('CKEditorFuncNum'), '', $error_msg);
                    break;
                default:
                    return json([
                        'code'   => 0,
                        'class'  => 'danger',
                        'info'   => $error_msg
                    ]);
            }
        }

        $config['domain'] = rtrim($config['domain'], '/').'/';
        $info = $file->move(config('upload_path') . DS . 'temp', '');
        $file_info = $file->getInfo();
        $accessKey = $config['ak'];
        $secretKey = $config['sk'];
        $filePath = $info->getPathname();
        $file_name = explode('.', $file_info['name']);
        $ext = end($file_name);
        $key = $info->hash('md5').'.'.$ext;
        $bucketConfig = new Config($config['bucket'], $accessKey, $secretKey);
        $client = new UpyunSdk($bucketConfig);
        $fileopen = fopen($filePath, 'r');
        $err = $client->write('/up/'.$key, $fileopen);
         //list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
        if (isset($err['code'])) {
            return json(['code' => 0, 'class' => 'danger', 'info' => '上传失败']);
        } else {
            // 获取附件信息
            $data = [
                'uid'    => session('user_auth.uid'),
                'name'   => $file_info['name'],
                'mime'   => $file_info['type'],
                'path'   => $config['domain'].'up/'.$key,
                'ext'    => $info->getExtension(),
                'size'   => $info->getSize(),
                'md5'    => $info->hash('md5'),
                'sha1'   => $info->hash('sha1'),
                'module' => $params['module'],
                'driver' => 'upyun',
            ];
            if ($file_add = AttachmentModel::create($data)) {
                unset($info);
                // 删除本地临时文件
                @unlink(config('upload_path') . DS . 'temp'.DS.$file_info['name']);
                switch ($params['from']) {
                    case 'wangeditor':
                        return $data['path'];
                        break;
                    case 'ueditor':
                        return json([
                            "state" => "SUCCESS",          // 上传状态，上传成功时必须返回"SUCCESS"
                            "url"   => $data['path'], // 返回的地址
                            "title" => $file_info['name'], // 附件名
                        ]);
                        break;
                    case 'editormd':
                        return json([
                            "success" => 1,
                            "message" => '上传成功',
                            "url"     => $data['path'],
                        ]);
                        break;
                    case 'ckeditor':
                        return ck_js(request()->get('CKEditorFuncNum'), $data['path']);
                        break;
                    default:
                        return json([
                            'code'   => 1,
                            'info'   => '上传成功',
                            'class'  => 'success',
                            'id'     => $file_add['id'],
                            'path'   => $data['path']
                        ]);
                }
            } else {
                switch ($params['from']) {
                    case 'wangeditor':
                        return "error|上传失败";
                        break;
                    case 'ueditor':
                        return json(['state' => '上传失败']);
                        break;
                    case 'editormd':
                        return json(["success" => 0, "message" => '上传失败']);
                        break;
                    case 'ckeditor':
                        return ck_js(request()->get('CKEditorFuncNum'), '', '上传失败');
                        break;
                    default:
                        return json(['code' => 0, 'class' => 'danger', 'info' => '上传失败']);
                }
            }
        }
    }

    /**
     * 安装方法
     * @author lsido <502354863@qq.com>
     * @return bool
     */
    public function install(){
        if (!version_compare(config('dolphin.product_version'), '1.0.6', '>=')) {
            $this->error = '本插件仅支持DolphinPHP1.0.6或以上版本';
            return false;
        }
        $upload_driver = Db::name('admin_config')->where(['name' => 'upload_driver', 'group' => 'upload'])->find();
        if (!$upload_driver) {
            $this->error = '未找到【上传驱动】配置，请确认DolphinPHP版本是否为1.0.6以上';
            return false;
        }
        $options = parse_attr($upload_driver['options']);
        if (isset($options['upyun'])) {
            $this->error = '已存在名为【upyun】的上传驱动';
            return false;
        }
        $upload_driver['options'] .= PHP_EOL.'upyun:又拍云';

        $result = Db::name('admin_config')
            ->where(['name' => 'upload_driver', 'group' => 'upload'])
            ->setField('options', $upload_driver['options']);

        if (false === $result) {
            $this->error = '上传驱动设置失败';
            return false;
        }
        return true;
    }

    /**
     * 卸载方法
     * @author lsido <502354863@qq.com>
     * @return bool
     */
    public function uninstall(){
        $upload_driver = Db::name('admin_config')->where(['name' => 'upload_driver', 'group' => 'upload'])->find();
        if ($upload_driver) {
            $options = parse_attr($upload_driver['options']);
            if (isset($options['upyun'])) {
                unset($options['upyun']);
            }
            $options = implode_attr($options);
            $result = Db::name('admin_config')
                ->where(['name' => 'upload_driver', 'group' => 'upload'])
                ->update(['options' => $options, 'value' => 'local']);

            if (false === $result) {
                $this->error = '上传驱动设置失败';
                return false;
            }
        }
        return true;
    }
}