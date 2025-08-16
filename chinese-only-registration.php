<?php
/**
 * Plugin Name: Chinese Only Registration
 * Plugin URI: https://zy.nuoyo.cn
 * Description: 只允许中文字符注册，禁止用户名包含字母和数字
 * Version: 1.0.2
 * Author: 诺言站长
 * License: GPL v2 or later
 * Text Domain: chinese-only-registration
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class ChineseOnlyRegistration {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {

        add_filter('registration_errors', array($this, 'registration_errors'), 10, 3);
        

        add_action('user_profile_update_errors', array($this, 'admin_user_profile_update_errors'), 10, 3);
        
  
        add_action('wp_footer', array($this, 'add_frontend_validation'));
        add_action('admin_footer', array($this, 'add_frontend_validation'));
        

        add_filter('validate_username', '__return_true', 999);
        

        add_filter('sanitize_user', array($this, 'custom_sanitize_user'), 999, 3);
        
        // 在用户创建前进行最终验证
        add_action('pre_user_login', array($this, 'pre_user_login_validation'));
        
        // 添加自定义的用户名验证函数
        add_filter('wp_pre_insert_user_data', array($this, 'pre_insert_user_data'), 10, 3);
        
        // 拦截用户注册过程
        add_action('register_post', array($this, 'intercept_registration'), 10, 3);
        
        // 加载文本域
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * 加载插件文本域
     */
    public function load_textdomain() {
        load_plugin_textdomain('chinese-only-registration', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * 检查字符串是否只包含中文字符
     */
    private function is_chinese_only($string) {
        // 如果字符串为空，返回false
        if (empty(trim($string))) {
            return false;
        }
        
        // 使用正则表达式检查是否只包含中文字符
        // \x{4e00}-\x{9fff} 是基本的中文汉字Unicode范围
        $pattern = '/^[\x{4e00}-\x{9fff}]+$/u';
        
        return preg_match($pattern, $string);
    }
    
    /**
     * 在注册时添加错误信息
     */
    public function registration_errors($errors, $sanitized_user_login, $user_email) {
        // 获取原始用户名（未经过WordPress清理）
        $raw_username = isset($_POST['user_login']) ? $_POST['user_login'] : $sanitized_user_login;
        
        // 如果用户名为空，跳过验证
        if (empty($raw_username)) {
            return $errors;
        }
        
        // 检查用户名是否只包含中文字符
        if (!$this->is_chinese_only($raw_username)) {
            $errors->add('username_invalid', 
                __('用户名只能包含中文字符，不允许使用字母、数字或其他符号。', 'chinese-only-registration')
            );
        }
        
        // 如果是中文字符，清除所有其他用户名相关的错误
        if ($this->is_chinese_only($raw_username)) {
            // 移除WordPress默认的用户名错误
            $errors->remove('username_invalid');
            $errors->remove('invalid_username');
            $errors->remove('username_exists');
        }
        
        return $errors;
    }
    
    /**
     * 在后台用户管理页面添加验证
     */
    public function admin_user_profile_update_errors($errors, $update, $user) {
        // 只在创建新用户时验证
        if ($update) {
            return;
        }
        
        $username = isset($_POST['user_login']) ? $_POST['user_login'] : '';
        
        if (!empty($username) && !$this->is_chinese_only($username)) {
            $errors->add('username_invalid', 
                __('用户名只能包含中文字符，不允许使用字母、数字或其他符号。', 'chinese-only-registration')
            );
        }
    }
    
    /**
     * 添加前端验证JavaScript
     */
    public function add_frontend_validation() {
        // 只在注册页面或用户管理页面添加验证
        if (is_admin() || is_page() || strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
            ?>
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                // 查找用户名输入框
                var usernameFields = document.querySelectorAll('input[name="user_login"], input[name="username"], input#user_login, input#username');
                
                usernameFields.forEach(function(field) {
                    field.addEventListener('blur', function() {
                        var username = this.value;
                        if (username) {
                            // 使用与服务器端相同的正则表达式
                            var pattern = /^[\u4e00-\u9fff]+$/u;
                            
                            if (!pattern.test(username)) {
                                alert('用户名只能包含中文字符，不允许使用字母、数字或其他符号。');
                                this.focus();
                            }
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * 在用户创建前进行最终验证
     */
    public function pre_user_login_validation($user_login) {
        // 检查用户名是否只包含中文字符
        if (!$this->is_chinese_only($user_login)) {
            // 如果不是中文字符，返回一个默认的中文用户名
            return '用户' . time();
        }
        
        return $user_login;
    }
    
    /**
     * 在插入用户数据前进行验证
     */
    public function pre_insert_user_data($data, $update, $id) {
        // 如果是更新操作，跳过验证
        if ($update) {
            return $data;
        }
        
        // 检查用户名是否只包含中文字符
        if (isset($data['user_login']) && !$this->is_chinese_only($data['user_login'])) {
            // 如果不是中文字符，生成一个默认的中文用户名
            $data['user_login'] = '用户' . time();
        }
        
        return $data;
    }
    
    /**
     * 拦截用户注册过程
     */
    public function intercept_registration($sanitized_user_login, $user_email, $errors) {
        // 获取原始用户名
        $raw_username = isset($_POST['user_login']) ? $_POST['user_login'] : $sanitized_user_login;
        
        // 检查用户名是否只包含中文字符
        if (!$this->is_chinese_only($raw_username)) {
            $errors->add('username_invalid', 
                __('用户名只能包含中文字符，不允许使用字母、数字或其他符号。', 'chinese-only-registration')
            );
        }
        
        return $errors;
    }
    
    /**
     * 修改用户名清理函数
     */
    public function custom_sanitize_user($username, $raw_username, $strict) {
        // 如果用户名为空，则绕过清理
        if (empty($username)) {
            return $username;
        }
        
        // 检查用户名是否只包含中文字符
        if ($this->is_chinese_only($username)) {
            return $username;
        }
        
        return $username; // 如果清理后不是中文，则返回原始用户名
    }
    
    /**
     * 插件激活时的操作
     */
    public static function activate() {
        // 可以在这里添加激活时需要执行的代码
    }
    
    /**
     * 插件停用时的操作
     */
    public static function deactivate() {
        // 可以在这里添加停用时需要执行的代码
    }
}

// 初始化插件
new ChineseOnlyRegistration();

// 注册激活和停用钩子
register_activation_hook(__FILE__, array('ChineseOnlyRegistration', 'activate'));
register_deactivation_hook(__FILE__, array('ChineseOnlyRegistration', 'deactivate'));

// 添加插件操作链接
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'chinese_only_registration_action_links');

function chinese_only_registration_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=chinese-only-registration') . '">' . __('设置', 'chinese-only-registration') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// 添加管理员设置页面
add_action('admin_menu', 'chinese_only_registration_admin_menu');

function chinese_only_registration_admin_menu() {
    add_options_page(
        '中文用户名注册设置',
        '中文用户名注册',
        'manage_options',
        'chinese-only-registration',
        'chinese_only_registration_options_page'
    );
}

function chinese_only_registration_options_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="card">
            <h2>插件信息</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">插件名称：</th>
                    <td>Chinese Only Registration</td>
                </tr>
                <tr>
                    <th scope="row">插件URL：</th>
                    <td><a href="https://zy.nuoyo.cn" target="_blank">https://zy.nuoyo.cn</a></td>
                </tr>
                <tr>
                    <th scope="row">作者：</th>
                    <td>诺言站长</td>
                </tr>
                <tr>
                    <th scope="row">版本：</th>
                    <td>1.0.2</td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2>插件说明</h2>
            <p>此插件限制用户注册时只能使用中文字符作为用户名。</p>
            <h3>功能特点：</h3>
            <ul>
                <li>✅ 只允许中文汉字字符</li>
                <li>❌ 禁止英文字母（a-z, A-Z）</li>
                <li>❌ 禁止数字（0-9）</li>
                <li>❌ 禁止特殊符号</li>
                <li>✅ 支持前台注册验证</li>
                <li>✅ 支持后台用户创建验证</li>
            </ul>
            <h3>支持的中文字符范围：</h3>
            <ul>
                <li>基本中文汉字（U+4E00-U+9FFF）</li>
            </ul>
        </div>
        
        <div class="card">
            <h2>测试用户名</h2>
            <p>您可以在下面测试用户名是否符合要求：</p>
            <input type="text" id="test-username" placeholder="输入要测试的用户名" style="width: 300px; padding: 8px;">
            <button type="button" id="test-button" class="button">测试</button>
            <div id="test-result" style="margin-top: 10px;"></div>
        </div>
        
        <div class="card">
            <h2>故障排除</h2>
            <p>如果仍然遇到"此用户名包含无效字符"的错误，请尝试以下步骤：</p>
            <ol>
                <li>确保插件已正确启用</li>
                <li>清除浏览器缓存</li>
                <li>尝试使用不同的中文字符组合</li>
                <li>检查WordPress版本是否为5.0或更高</li>
                <li>检查PHP版本是否为7.0或更高</li>
            </ol>
        </div>
    </div>
    
    <script>
    document.getElementById('test-button').addEventListener('click', function() {
        var username = document.getElementById('test-username').value;
        var resultDiv = document.getElementById('test-result');
        
        if (!username) {
            resultDiv.innerHTML = '<p style="color: #d63638;">❌ 请输入用户名</p>';
            return;
        }
        
        // 简单的客户端验证（与服务器端逻辑相同）
        var pattern = /^[\u4e00-\u9fff]+$/u;
        
        if (pattern.test(username)) {
            resultDiv.innerHTML = '<p style="color: #00a32a;">✅ 用户名符合要求，只包含中文字符</p>';
        } else {
            resultDiv.innerHTML = '<p style="color: #d63638;">❌ 用户名不符合要求，包含非中文字符</p>';
        }
    });
    </script>
    <?php
}
