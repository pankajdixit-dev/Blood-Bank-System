<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

Class Action {
	private $db;

	public function __construct() {
		if (ob_get_level() == 0) {
			ob_start();
		}
		include 'db_connect.php';
		$this->db = (isset($conn) && $conn instanceof mysqli && empty($conn->connect_error)) ? $conn : null;
	}

	function __destruct() {
		if ($this->db && $this->db instanceof mysqli && !empty($this->db->thread_id)) {
			@$this->db->close();
		}
		if (ob_get_level() > 0) {
			ob_end_flush();
		}
	}

	function login(){
		extract($_POST);
		if(!isset($username) || !isset($password) || !$this->db) {
			return 3;
		}
		$username = $this->db->real_escape_string($username);
		$qry = $this->db->query("SELECT * FROM users where username = '".$username."' and password = '".md5($password)."' ");
		if($qry && $qry instanceof mysqli_result && $qry->num_rows > 0){
			foreach ($qry->fetch_array() as $key => $value) {
				if($key != 'password' && !is_numeric($key))
					$_SESSION['login_'.$key] = $value;
			}
			return 1;
		}else{
			return 3;
		}
	}

	function login2(){
		extract($_POST);
		if(!$this->db || !isset($password)) {
			return 3;
		}
		if(isset($email))
			$username = $email;
		if(!isset($username)) {
			return 3;
		}
		$username = $this->db->real_escape_string($username);
		$qry = $this->db->query("SELECT * FROM users where username = '".$username."' and password = '".md5($password)."' ");
		if($qry && $qry instanceof mysqli_result && $qry->num_rows > 0){
			foreach ($qry->fetch_array() as $key => $value) {
				if($key != 'password' && !is_numeric($key))
					$_SESSION['login_'.$key] = $value;
			}
			if(isset($_SESSION['login_alumnus_id']) && (int)$_SESSION['login_alumnus_id'] > 0){
				$bio = $this->db->query("SELECT * FROM alumnus_bio where id = ".(int)$_SESSION['login_alumnus_id']);
				if($bio && $bio instanceof mysqli_result && $bio->num_rows > 0){
					foreach ($bio->fetch_array() as $key => $value) {
						if($key != 'password' && !is_numeric($key))
							$_SESSION['bio'][$key] = $value;
					}
				}
			}
			if(isset($_SESSION['bio']['status']) && $_SESSION['bio']['status'] != 1){
				foreach ($_SESSION as $key => $value) {
					unset($_SESSION[$key]);
				}
				return 2;
			}
			return 1;
		}else{
			return 3;
		}
	}

	function logout(){
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
		session_destroy();
		$_SESSION = array();
		header("location:login.php");
		exit;
	}

	function logout2(){
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
		session_destroy();
		$_SESSION = array();
		header("location:index.php");
		exit;
	}

	function save_user(){
		extract($_POST);
		if(!$this->db) return 0;
		$name = isset($name) ? $this->db->real_escape_string($name) : '';
		$username = isset($username) ? $this->db->real_escape_string($username) : '';
		$type = isset($type) ? (int)$type : 1;
		$id = isset($id) ? (int)$id : 0;

		$data = " name = '$name' ";
		$data .= ", username = '$username' ";
		if(!empty($password))
			$data .= ", password = '".md5($password)."' ";
		$data .= ", type = '$type' ";
		
		$chk_res = $this->db->query("SELECT * FROM users where username = '$username' and id !='$id' ");
		$chk = ($chk_res && $chk_res instanceof mysqli_result) ? $chk_res->num_rows : 0;
		if($chk > 0){
			return 2;
		}
		if(empty($id)){
			$save = $this->db->query("INSERT INTO users set ".$data);
		}else{
			$save = $this->db->query("UPDATE users set ".$data." where id = ".$id);
		}
		if($save){
			return 1;
		}
		return 0;
	}

	function delete_user(){
		extract($_POST);
		if(!$this->db || !isset($id)) return 0;
		$id = (int)$id;
		$delete = $this->db->query("DELETE FROM users where id = ".$id);
		if($delete)
			return 1;
		return 0;
	}

	function signup(){
		extract($_POST);
		if(!$this->db || !isset($email) || !isset($password)) return 0;
		$firstname = isset($firstname) ? $this->db->real_escape_string($firstname) : '';
		$lastname = isset($lastname) ? $this->db->real_escape_string($lastname) : '';
		$email = $this->db->real_escape_string($email);
		
		$data = " name = '".$firstname.' '.$lastname."' ";
		$data .= ", username = '$email' ";
		$data .= ", password = '".md5($password)."' ";
		$chk_res = $this->db->query("SELECT * FROM users where username = '$email' ");
		$chk = ($chk_res && $chk_res instanceof mysqli_result) ? $chk_res->num_rows : 0;
		if($chk > 0){
			return 2;
		}
		$save = $this->db->query("INSERT INTO users set ".$data);
		if($save){
			$uid = $this->db->insert_id;
			$data = '';
			foreach($_POST as $k => $v){
				if($k =='password')
					continue;
				$v_esc = $this->db->real_escape_string($v);
				if(empty($data) && !is_numeric($k) )
					$data = " $k = '$v_esc' ";
				else
					$data .= ", $k = '$v_esc' ";
			}
			if(isset($_FILES['img']['tmp_name']) && $_FILES['img']['tmp_name'] != ''){
				$fname = strtotime(date('y-m-d H:i')).'_'.basename($_FILES['img']['name']);
				if (!is_dir('assets/uploads')) {
					@mkdir('assets/uploads', 0777, true);
				}
				@move_uploaded_file($_FILES['img']['tmp_name'],'assets/uploads/'. $fname);
				$data .= ", avatar = '$fname' ";
			}
			$save_alumni = $this->db->query("INSERT INTO alumnus_bio set $data ");
			if($save_alumni){
				$aid = $this->db->insert_id;
				$this->db->query("UPDATE users set alumnus_id = $aid where id = $uid ");
				$login = $this->login2();
				if($login)
					return 1;
			}
			return 1;
		}
		return 0;
	}

	function update_account(){
		extract($_POST);
		if(!$this->db || !isset($_SESSION['login_id'])) return 0;
		$firstname = isset($firstname) ? $this->db->real_escape_string($firstname) : '';
		$lastname = isset($lastname) ? $this->db->real_escape_string($lastname) : '';
		$email = isset($email) ? $this->db->real_escape_string($email) : '';
		$login_id = (int)$_SESSION['login_id'];

		$data = " name = '".$firstname.' '.$lastname."' ";
		$data .= ", username = '$email' ";
		if(!empty($password))
			$data .= ", password = '".md5($password)."' ";
		$chk_res = $this->db->query("SELECT * FROM users where username = '$email' and id != '$login_id' ");
		$chk = ($chk_res && $chk_res instanceof mysqli_result) ? $chk_res->num_rows : 0;
		if($chk > 0){
			return 2;
		}
		$save = $this->db->query("UPDATE users set $data where id = '$login_id' ");
		if($save){
			$data = '';
			foreach($_POST as $k => $v){
				if($k =='password')
					continue;
				$v_esc = $this->db->real_escape_string($v);
				if(empty($data) && !is_numeric($k) )
					$data = " $k = '$v_esc' ";
				else
					$data .= ", $k = '$v_esc' ";
			}
			if(isset($_FILES['img']['tmp_name']) && $_FILES['img']['tmp_name'] != ''){
				$fname = strtotime(date('y-m-d H:i')).'_'.basename($_FILES['img']['name']);
				if (!is_dir('assets/uploads')) {
					@mkdir('assets/uploads', 0777, true);
				}
				@move_uploaded_file($_FILES['img']['tmp_name'],'assets/uploads/'. $fname);
				$data .= ", avatar = '$fname' ";
			}
			if(isset($_SESSION['bio']['id'])){
				$bio_id = (int)$_SESSION['bio']['id'];
				$save_alumni = $this->db->query("UPDATE alumnus_bio set $data where id = '$bio_id' ");
			}
			return 1;
		}
		return 0;
	}

	function save_settings(){
		extract($_POST);
		if(!$this->db) return 0;
		$name = isset($name) ? $this->db->real_escape_string(str_replace("'","&#x2019;",$name)) : '';
		$email = isset($email) ? $this->db->real_escape_string($email) : '';
		$contact = isset($contact) ? $this->db->real_escape_string($contact) : '';
		$about = isset($about) ? $this->db->real_escape_string(htmlentities(str_replace("'","&#x2019;",$about))) : '';

		$data = " name = '$name' ";
		$data .= ", email = '$email' ";
		$data .= ", contact = '$contact' ";
		$data .= ", about_content = '$about' ";
		if(isset($_FILES['img']['tmp_name']) && $_FILES['img']['tmp_name'] != ''){
			$fname = strtotime(date('y-m-d H:i')).'_'.basename($_FILES['img']['name']);
			if (!is_dir('assets/uploads')) {
				@mkdir('assets/uploads', 0777, true);
			}
			@move_uploaded_file($_FILES['img']['tmp_name'],'assets/uploads/'. $fname);
			$data .= ", cover_img = '$fname' ";
		}
		
		$chk = $this->db->query("SELECT * FROM system_settings");
		if($chk && $chk instanceof mysqli_result && $chk->num_rows > 0){
			$save = $this->db->query("UPDATE system_settings set ".$data);
		}else{
			$save = $this->db->query("INSERT INTO system_settings set ".$data);
		}
		if($save){
			$query_res = $this->db->query("SELECT * FROM system_settings limit 1");
			if($query_res && $query_res instanceof mysqli_result && $query_res->num_rows > 0){
				$query = $query_res->fetch_array();
				foreach ($query as $key => $value) {
					if(!is_numeric($key))
						$_SESSION['system'][$key] = $value;
				}
			}
			return 1;
		}
		return 0;
	}

	function save_donor(){
		extract($_POST);
		if(!$this->db) return 0;
		$name = isset($name) ? $this->db->real_escape_string($name) : '';
		$address = isset($address) ? $this->db->real_escape_string($address) : '';
		$email = isset($email) ? $this->db->real_escape_string($email) : '';
		$contact = isset($contact) ? $this->db->real_escape_string($contact) : '';
		$blood_group = isset($blood_group) ? $this->db->real_escape_string($blood_group) : '';
		$id = isset($id) ? (int)$id : 0;

		$data = " name = '$name' ";
		$data .= ", address = '$address' ";
		$data .= ", email = '$email' ";
		$data .= ", contact = '$contact' ";
		$data .= ", blood_group = '$blood_group' ";
		if(empty($id)){
			$save = $this->db->query("INSERT INTO donors set $data");
		}else{
			$save = $this->db->query("UPDATE donors set $data where id = $id");
		}
		if($save)
			return 1;
		return 0;
	}

	function delete_donor(){
		extract($_POST);
		if(!$this->db || !isset($id)) return 0;
		$id = (int)$id;
		$delete = $this->db->query("DELETE FROM donors where id = ".$id);
		if($delete){
			return 1;
		}
		return 0;
	}

	function save_donation(){
		extract($_POST);
		if(!$this->db) return 0;
		$donor_id = isset($donor_id) ? (int)$donor_id : 0;
		$blood_group = isset($blood_group) ? $this->db->real_escape_string($blood_group) : '';
		$status = isset($status) ? (int)$status : 1;
		$volume = isset($volume) ? (float)$volume : 0;
		$date_created = isset($date_created) ? $this->db->real_escape_string($date_created) : date('Y-m-d H:i:s');
		$id = isset($id) ? (int)$id : 0;

		$data = " donor_id = '$donor_id' ";
		$data .= ", blood_group = '$blood_group' ";
		$data .= ", status = '$status' ";
		$data .= ", volume = '$volume' ";
		$data .= ", date_created = '$date_created' ";
		if(empty($id)){
			$save = $this->db->query("INSERT INTO blood_inventory set $data");
		}else{
			$save = $this->db->query("UPDATE blood_inventory set $data where id = $id");
		}
		if($save)
			return 1;
		return 0;
	}

	function delete_donation(){
		extract($_POST);
		if(!$this->db || !isset($id)) return 0;
		$id = (int)$id;
		$delete = $this->db->query("DELETE FROM blood_inventory where id = ".$id);
		if($delete){
			return 1;
		}
		return 0;
	}

	function save_request(){
		extract($_POST);
		if(!$this->db) return 0;
		$patient = isset($patient) ? $this->db->real_escape_string($patient) : '';
		$blood_group = isset($blood_group) ? $this->db->real_escape_string($blood_group) : '';
		$physician_name = isset($physician_name) ? $this->db->real_escape_string($physician_name) : '';
		$vol_calc = isset($volume) ? ((float)$volume * 1000) : 0;
		$id = isset($id) ? (int)$id : 0;

		$data = " patient = '$patient' ";
		$data .= ", blood_group = '$blood_group' ";
		$data .= ", physician_name = '$physician_name' ";
		$data .= ", volume = '$vol_calc' ";
		if(isset($status))
			$data .= ", status = '".(int)$status."' ";
		
		if(empty($id)){
			$i = 1;
			while($i == 1){
				$rand = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
				$ref_code = substr(str_shuffle($rand), 0,8);
				$chk_res = $this->db->query("SELECT * FROM requests where ref_code = '$ref_code'");
				$chk = ($chk_res && $chk_res instanceof mysqli_result) ? $chk_res->num_rows : 0;
				if($chk <= 0){
					$i = 0;
					$data .= ", ref_code = '$ref_code' ";
				}
			}
			$save = $this->db->query("INSERT INTO requests set $data");
		}else{
			$save = $this->db->query("UPDATE requests set $data where id = $id");
		}
		if($save)
			return 1;
		return 0;
	}

	function delete_request(){
		extract($_POST);
		if(!$this->db || !isset($id)) return 0;
		$id = (int)$id;
		$delete = $this->db->query("DELETE FROM requests where id = ".$id);
		if($delete){
			return 1;
		}
		return 0;
	}

	function get_available(){
		extract($_POST);
		if(!$this->db) return 0;
		$where = '';
		if(!empty($id)){
			$where = " and request_id != ".(int)$id;
		}
		$blood_group = isset($blood_group) ? $this->db->real_escape_string($blood_group) : '';
		$inn_qry = $this->db->query("SELECT sum(volume) as total from blood_inventory where blood_group = '$blood_group' and status = 1 ");
		$inn = ($inn_qry && $inn_qry instanceof mysqli_result && $inn_qry->num_rows > 0) ? (float)$inn_qry->fetch_array()['total'] : 0;
		$out_qry = $this->db->query("SELECT sum(volume) as total from blood_inventory where blood_group = '$blood_group' and status = 2 $where ");
		$out = ($out_qry && $out_qry instanceof mysqli_result && $out_qry->num_rows > 0) ? (float)$out_qry->fetch_array()['total'] : 0;

		$available = $inn - $out;
		$available = $available / 1000;
		return $available;
	}

	function chk_request(){
		extract($_POST);
		$data = array();
		if(!$this->db) {
			$data['status'] = 0;
			return json_encode($data);
		}
		$ref_code = isset($ref_code) ? $this->db->real_escape_string($ref_code) : '';
		$qry = $this->db->query("SELECT * FROM requests where ref_code = '$ref_code'");
		if($qry && $qry instanceof mysqli_result && $qry->num_rows > 0){
			$result = $qry->fetch_array();
			if($result['status'] == 0){
				$data['status'] = 2;
			}else{
				$id_check = (isset($id) && (int)$id > 0) ? " and hr.id != ".(int)$id : "";
				$chk_qry = $this->db->query("SELECT * FROM handedover_request hr inner join requests r on r.id = hr.request_id where r.ref_code = '$ref_code' $id_check ");
				$chk = ($chk_qry && $chk_qry instanceof mysqli_result) ? $chk_qry->num_rows : 0;
				if($chk > 0){
					$data['status'] = 3;
				}else{
					$data['status'] = 1;
					foreach($result as $k => $v){
						if(!is_numeric($k)){
							$data['data'][$k] = $v;
						}
					}
				}
			}
		}else{
			$data['status'] = 0;
		}
		if(isset($data['data']['volume']))
			$data['data']['volumeL'] = $data['data']['volume'] / 1000; 	
		return json_encode($data);
	}

	function save_handover(){
		extract($_POST);
		if(!$this->db) return 0;
		$data = "";
		foreach($_POST as $k => $v){
			if(!in_array($k, array('id','ref_code')) && !is_numeric($k)){
				$v_esc = $this->db->real_escape_string($v);
				if(empty($data)){
					$data .= " $k='$v_esc' ";
				}else{
					$data .= ", $k='$v_esc' ";
				}
			}
		}
		$id = isset($id) ? (int)$id : 0;
		if(empty($id)){
			$save = $this->db->query("INSERT INTO handedover_request set $data");
			$id = $this->db->insert_id;
		}else{
			$save = $this->db->query("UPDATE handedover_request set $data where id = $id");
		}

		if($save){
			$ref_code = isset($ref_code) ? $this->db->real_escape_string($ref_code) : '';
			$req_res = $this->db->query("SELECT * FROM requests where ref_code = '$ref_code' ");
			if($req_res && $req_res instanceof mysqli_result && $req_res->num_rows > 0){
				$request = $req_res->fetch_array();
				$bg_esc = $this->db->real_escape_string($request['blood_group']);
				$vol_esc = (float)$request['volume'];
				$req_id = (int)$request['id'];

				$inv_data = " blood_group = '$bg_esc' ";
				$inv_data .= ", volume = '$vol_esc' ";
				$inv_data .= ", request_id = '$req_id' ";
				$inv_data .= ", status = '2' ";
				$chk_inv = $this->db->query("SELECT * FROM blood_inventory where request_id= '$req_id' ");
				$chk = ($chk_inv && $chk_inv instanceof mysqli_result) ? $chk_inv->num_rows : 0;
				if($chk > 0){
					$this->db->query("UPDATE blood_inventory set $inv_data where request_id= '$req_id' ");
				}else{
					$this->db->query("INSERT INTO blood_inventory set $inv_data");
				}
			}
			return 1;
		}
		return 0;
	}

	function delete_handover(){
		extract($_POST);
		if(!$this->db || !isset($id)) return 0;
		$id = (int)$id;
		$req_qry = $this->db->query("SELECT * FROM handedover_request where id= '$id' ");
		if($req_qry && $req_qry instanceof mysqli_result && $req_qry->num_rows > 0){
			$request_id = (int)$req_qry->fetch_array()['request_id'];
			$delete = $this->db->query("DELETE FROM handedover_request where id = ".$id);
			if($delete){
				$this->db->query("DELETE FROM blood_inventory where request_id= '$request_id' ");
				return 1;
			}
		}
		return 0;
	}
}
