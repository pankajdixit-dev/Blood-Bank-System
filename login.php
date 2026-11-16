<?php 
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./db_connect.php');

$db_ok = false;
$tables_exist = false;

if (isset($conn) && $conn instanceof mysqli && empty($conn->connect_error)) {
    $db_ok = true;
    try {
        $chk_table = @$conn->query("SHOW TABLES LIKE 'system_settings'");
        if ($chk_table && $chk_table instanceof mysqli_result && $chk_table->num_rows > 0) {
            $tables_exist = true;
            if(!isset($_SESSION['system'])){
                $sys_qry = @$conn->query("SELECT * FROM system_settings limit 1");
                if($sys_qry && $sys_qry instanceof mysqli_result && $sys_qry->num_rows > 0){
                    $system = $sys_qry->fetch_array();
                    foreach($system as $k => $v){
                        if(!is_numeric($k))
                            $_SESSION['system'][$k] = $v;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        $tables_exist = false;
    }
}

if (!isset($_SESSION['system']['name']) || empty($_SESSION['system']['name'])) {
    $_SESSION['system']['name'] = "Blood Bank Management System";
}

if(isset($_SESSION['login_id'])){
    header("location:index.php?page=home");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title><?php echo htmlspecialchars($_SESSION['system']['name']) ?></title>

<?php include('./header.php'); ?>

</head>
<style>
	body{
		width: 100%;
	    height: 100vh;
	}
	main#main{
		width:100%;
		height: 100%;
		background:white;
	}
	#login-right{
		position: absolute;
		right:0;
		width:40%;
		height: 100%;
		background:#c82333;
		display: flex;
		align-items: center;
	}
	#login-left{
		position: absolute;
		left:0;
		width:60%;
		height: 100%;
		background:#59b6ec61;
		display: flex;
		align-items: center;
		background: url('assets/uploads/blood-cells.jpg') center center / cover no-repeat, linear-gradient(135deg, #780206, #061161);
	}
	#login-right .card{
		margin: auto;
		z-index: 1;
		box-shadow: 0 4px 15px rgba(0,0,0,0.2);
	}
	.logo {
		margin: auto;
		font-size: 8rem;
		background: white;
		padding: .5em 0.7em;
		border-radius: 50%;
		color: #000000b3;
		z-index: 10;
	}
	@media (max-width: 768px) {
		#login-left { display: none; }
		#login-right { width: 100%; }
	}
</style>

<body>
  <main id="main" class="bg-danger">
  		<div id="login-left">
  		</div>

  		<div id="login-right" class="bg-danger">
  			<div class="w-100 p-3">
			<h4 class="text-white text-center mb-3"><b><?php echo htmlspecialchars($_SESSION['system']['name']) ?></b></h4>
  			<div class="card col-md-8 mx-auto">
  				<div class="card-body">

					<?php if (isset($_GET['setup']) && $_GET['setup'] == 'success'): ?>
					<div class="alert alert-success py-2 small text-center">
						<i class="fa fa-check-circle mr-1"></i> <strong>Success!</strong> Database imported successfully.<br>
						You can now login with <strong>admin / admin123</strong>.
					</div>
					<?php endif; ?>

					<?php if (!$db_ok || (isset($conn) && $conn->connect_error)): ?>
					<div class="alert alert-warning py-2 small text-center">
						<strong>Notice:</strong> Database is not connected.<br>
						<a href="db_setup.php" class="btn btn-warning btn-sm mt-1 font-weight-bold">Configure Database</a>
					</div>
					<?php elseif (!$tables_exist): ?>
					<div class="alert alert-warning py-2 small text-center">
						<strong>Aiven DB Connected:</strong> Tables not found in <code><?php echo htmlspecialchars($db_name); ?></code>.<br>
						<a href="db_setup.php?auto=1" class="btn btn-danger btn-sm mt-2 font-weight-bold"><i class="fa fa-cloud-upload-alt mr-1"></i> 1-Click Import Database Tables</a>
					</div>
					<?php endif; ?>

  					<form id="login-form">
  						<div class="form-group">
  							<label for="username" class="control-label">Username</label>
  							<input type="text" id="username" name="username" class="form-control" required autocomplete="username">
  						</div>
  						<div class="form-group">
  							<label for="password" class="control-label">Password</label>
  							<input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
  						</div>
  						<center><button type="submit" class="btn-sm btn-block btn-wave col-md-6 btn-primary">Login</button></center>
  					</form>
					<div class="mt-3 text-center">
						<small><a href="db_setup.php" class="text-muted"><i class="fa fa-database"></i> Database Health & Setup Tool</a></small>
					</div>
  				</div>
  			</div>
  			</div>
  		</div>
  </main>

  <a href="#" class="back-to-top"><i class="icofont-simple-up"></i></a>

<script>
	$('#login-form').submit(function(e){
		e.preventDefault();
		var btn = $(this).find('button[type="submit"]');
		btn.attr('disabled', true).html('Logging in...');
		if($(this).find('.alert-danger').length > 0 )
			$(this).find('.alert-danger').remove();
		$.ajax({
			url:'ajax.php?action=login',
			method:'POST',
			data:$(this).serialize(),
			error:function(err){
				console.log(err);
				$('#login-form').prepend('<div class="alert alert-danger">Error connecting to server. Please check database settings.</div>');
				btn.removeAttr('disabled').html('Login');
			},
			success:function(resp){
				if(resp == 1){
					location.href = 'index.php?page=home';
				}else{
					$('#login-form').prepend('<div class="alert alert-danger">Username or password is incorrect.</div>');
					btn.removeAttr('disabled').html('Login');
				}
			}
		});
	});
</script>	
</body>
</html>
<?php
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
