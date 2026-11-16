<?php 
include 'db_connect.php'; 
if(isset($_GET['id']) && isset($conn) && $conn instanceof mysqli && empty($conn->connect_error)){
    $qry = $conn->query("SELECT * FROM requests where id= ".(int)$_GET['id']);
    if($qry && $qry instanceof mysqli_result && $qry->num_rows > 0){
        foreach($qry->fetch_array() as $k => $val){
            $$k=$val;
        }
    }
}
?>
<div class="container-fluid">
	<form action="" id="manage-request">
		<input type="hidden" name="id" value="<?php echo isset($id) ? $id : '' ?>">
		<div id="msg"></div>
		<div class="form-group">
			<label for="" class="control-label">Patient Full Name</label>
			<input type="text" class="form-control" name="patient"  value="<?php echo isset($patient) ? htmlspecialchars($patient) :'' ?>" required>
		</div>
		<div class="form-group">
	        <label for="" class="control-label">Blood Group</label>
			<select name="blood_group" id="" class="custom-select select2" required>
				<option value=""></option>
				<option <?php echo isset($blood_group) && $blood_group == 'A+' ? ' selected' : '' ?>>A+</option>
				<option <?php echo isset($blood_group) && $blood_group == 'B+' ? ' selected' : '' ?>>B+</option>
				<option <?php echo isset($blood_group) && $blood_group == 'O+' ? ' selected' : '' ?>>O+</option>
				<option <?php echo isset($blood_group) && $blood_group == 'AB+' ? ' selected' : '' ?>>AB+</option>
				<option <?php echo isset($blood_group) && $blood_group == 'A-' ? ' selected' : '' ?>>A-</option>
				<option <?php echo isset($blood_group) && $blood_group == 'B-' ? ' selected' : '' ?>>B-</option>
				<option <?php echo isset($blood_group) && $blood_group == 'O-' ? ' selected' : '' ?>>O-</option>
				<option <?php echo isset($blood_group) && $blood_group == 'AB-' ? ' selected' : '' ?>>AB-</option>
			</select>
		</div>

		<div class="form-group">
			<label for="" class="control-label">Available Volume (L)</label>
			<input type="number" class="form-control text-right" step="any" name="avolume"  value="0" readonly="">
			<small class="text-muted"><i class="fa fa-info-circle"></i> Available volume is auto-calculated from current stock in <b>Blood Donations</b>.</small>
		</div>

		<div class="form-group">
			<label for="" class="control-label">Volume (L)</label>
			<input type="number" class="form-control" name="volume"  value="<?php echo isset($volume) ? $volume / 1000 :'' ?>" required>
		</div>
		<div class="form-group">
			<label for="" class="control-label">Physician Name</label>
			<input type="text" class="form-control" name="physician_name"  value="<?php echo isset($physician_name) ? htmlspecialchars($physician_name) :'' ?>" required>
		</div>
		<?php if(isset($status)): ?>
		<div class="form-group">
	        <label for="" class="control-label">Status</label>
			<select name="status" id="" class="custom-select select2" required>
				<option value="0" <?php echo $status == 0 ? 'selected' : ''  ?>>Pending</option>
				<option value="1" <?php echo $status == 1 ? 'selected' : ''  ?>>Approved</option>
			</select>
		</div>
		<?php endif; ?>
	</form>
</div>
<script>
	$('.select2').select2({
		placeholder:'Please select here.',
		width:'100%'
	})
	$(document).ready(function(){
		if('<?php echo isset($blood_group)? 1:0 ?>' == 1)
			$('[name="blood_group"]').trigger('change')	
	})
	$('[name="blood_group"]').change(function(){
		var _b = $(this).val()
		if(_b != ''){
			$('[name="avolume"]').val('')
			$.ajax({
				url:'ajax.php?action=get_available',
				method:'POST',
				data:{blood_group:$(this).val(),id:'<?php echo isset($id)? $id : '' ?>'},
				success:function(resp){
					if(resp > 0){
						$('[name="avolume"]').val(resp)
					}else{
						$('[name="avolume"]').val('0')
					}
				}
			})
		}else{
			$('[name="avolume"]').val('0')
		}
	})
	$('#manage-request').submit(function(e){
		e.preventDefault()
		start_load()
		$('#msg').html('')
		var req_vol = parseFloat($('[name="volume"]').val()) || 0;
		var avail_vol = parseFloat($('[name="avolume"]').val()) || 0;
		if(req_vol > avail_vol){
			if(!confirm("Notice: Blood requested volume (" + req_vol + " L) is greater than available stock (" + avail_vol + " L).\n\nDo you want to save this request as 'Pending' anyway?")){
				end_load()
				return false;
			}
		}
		$.ajax({
			url:'ajax.php?action=save_request',
			data: new FormData($(this)[0]),
		    cache: false,
		    contentType: false,
		    processData: false,
		    method: 'POST',
		    type: 'POST',
			success:function(resp){
				if(resp==1){
					alert_toast("Data successfully saved.",'success')
						setTimeout(function(){
							location.reload()
						},1000)
				}
			}
		})
	})
</script>
