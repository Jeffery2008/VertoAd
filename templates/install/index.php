<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - VertoAD</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/toastr.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .install-container {
            max-width: 800px;
            margin: 50px auto;
        }
        .system-check {
            margin-bottom: 20px;
        }
        .check-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .check-status {
            font-weight: bold;
        }
        .check-status.pass {
            color: #28a745;
        }
        .check-status.fail {
            color: #dc3545;
        }
        .progress {
            height: 25px;
        }
    </style>
</head>
<body>
    <div class="container install-container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">VertoAD 系统安装</h4>
            </div>
            <div class="card-body">
                <div id="stepIndicator" class="alert alert-info" role="alert">
                    请填写以下信息以完成系统安装
                </div>

                <div class="system-check">
                    <h5>系统环境检查</h5>
                    <?php foreach ($systemChecks as $check): ?>
                    <div class="check-item">
                        <span><?php echo $check['name']; ?></span>
                        <span class="check-status <?php echo $check['passed'] ? 'pass' : 'fail'; ?>">
                            <?php echo $check['passed'] ? '通过' : '未通过'; ?>
                            <?php if (!$check['passed']): ?>
                                <i class="fas fa-exclamation-circle" title="<?php echo $check['message']; ?>"></i>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="progress mb-4" id="installationProgress" style="display: none;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                </div>

                <form id="installForm" method="post" action="/install">
                    <h5 class="mb-3">数据库配置</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="dbHost">数据库主机</label>
                                <input type="text" class="form-control" id="dbHost" name="db_host" value="localhost" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="dbPort">端口</label>
                                <input type="number" class="form-control" id="dbPort" name="db_port" value="3306" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="dbName">数据库名</label>
                                <input type="text" class="form-control" id="dbName" name="db_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="dbPrefix">表前缀</label>
                                <input type="text" class="form-control" id="dbPrefix" name="db_prefix" value="vad_">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="dbUser">用户名</label>
                                <input type="text" class="form-control" id="dbUser" name="db_user" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="dbPass">密码</label>
                                <input type="password" class="form-control" id="dbPass" name="db_pass" required>
                            </div>
                        </div>
                    </div>

                    <h5 class="mb-3 mt-4">管理员账号</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="adminUser">用户名</label>
                                <input type="text" class="form-control" id="adminUser" name="admin_user" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="adminEmail">邮箱</label>
                                <input type="email" class="form-control" id="adminEmail" name="admin_email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="adminPass">密码</label>
                                <input type="password" class="form-control" id="adminPass" name="admin_pass" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="adminPassConfirm">确认密码</label>
                                <input type="password" class="form-control" id="adminPassConfirm" name="admin_pass_confirm" required>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">开始安装</button>
                    </div>
                </form>

                <div id="installationResult" class="mt-4" style="display: none;"></div>
            </div>
        </div>
    </div>

    <script src="/assets/js/jquery.min.js"></script>
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/toastr.min.js"></script>
    <script>
    $(document).ready(function() {
        // 配置toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "timeOut": "3000"
        };
        
        // 表单提交
        $('#installForm').on('submit', function(e) {
            e.preventDefault();
            
            // 验证密码确认
            if ($('#adminPass').val() !== $('#adminPassConfirm').val()) {
                toastr.error('两次输入的密码不一致');
                return;
            }
            
            var $form = $(this);
            var $submitButton = $form.find('button[type="submit"]');
            var $progressBar = $('#installationProgress');
            var $progressBarInner = $progressBar.find('.progress-bar');
            var $stepIndicator = $('#stepIndicator');
            
            // 显示进度条
            $progressBar.show();
            $progressBarInner.css('width', '0%');
            
            // 禁用表单
            $form.find('input, select, button').prop('disabled', true);
            $submitButton.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 安装中...');
            
            // 更新进度提示
            $stepIndicator.removeClass('alert-info alert-danger').addClass('alert-info').text('正在安装...');
            
            // 发送安装请求
            $.ajax({
                url: '/install',
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $progressBarInner.css('width', '100%');
                        $stepIndicator.removeClass('alert-info alert-danger').addClass('alert-success')
                            .html('安装成功！<br>系统将在 <span id="countdown">3</span> 秒后跳转到登录页面...');
                        
                        // 倒计时跳转
                        var countdown = 3;
                        var timer = setInterval(function() {
                            countdown--;
                            $('#countdown').text(countdown);
                            if (countdown <= 0) {
                                clearInterval(timer);
                                window.location.href = '/admin/login';
                            }
                        }, 1000);
                    } else {
                        $stepIndicator.removeClass('alert-info alert-success').addClass('alert-danger')
                            .text('安装失败：' + response.message);
                        // 启用表单
                        $form.find('input, select, button').prop('disabled', false);
                        $submitButton.html('开始安装');
                    }
                },
                error: function() {
                    $stepIndicator.removeClass('alert-info alert-success').addClass('alert-danger')
                        .text('安装失败：服务器错误');
                    // 启用表单
                    $form.find('input, select, button').prop('disabled', false);
                    $submitButton.html('开始安装');
                }
            });
        });
    });
    </script>
</body>
</html> 