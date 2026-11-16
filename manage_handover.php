<?php 
include 'db_connect.php'; 
if(isset($_GET['id']) && isset($conn) && $conn instanceof mysqli && empty($conn->connect_error)){
    $qry = $conn->query("SELECT hr.*,r.patient,r.blood_group, r.volume,r.ref_code FROM handedover_request hr inner join requests r on r.id = hr.request_id where hr.id= ".(int)$_GET['id']);
    if($qry && $qry instanceof mysqli_result && $qry->num_rows > 0){
        foreach($qry->fetch_array() as $k => $val){
            $$k=$val;
        }
    }
}
?>
<div class="container-fluid">
    <form action="" id="manage-handover">
        <input type="hidden" name="id" value="<?php echo isset($id) ? $id : '' ?>">
        <div id="msg"></div>
        <div class="form-group">
            <label for="" class="control-label">Request's Reference Code</label>
            <input type="text" class="form-control" name="ref_code"  value="<?php echo isset($ref_code) ? htmlspecialchars($ref_code) :'' ?>" required>
        </div>
        <div class="form-group">
            <button class="btn btn-sm btn-primary" type="button" id="chk_request">Check</button>
        </div>
        <div class="form-group" id="request_details">
            
        </div>

        <div class="form-group" style="display: none">
            <label for="" class="control-label">Received by: </label>
            <input type="text" class="form-control" name="picked_up_by"  value="<?php echo isset($picked_up_by) ? htmlspecialchars($picked_up_by) :'' ?>" >
        </div>
    </form>
</div>
<script>
    $(document).ready(function(){
        if('<?php echo isset($id)? 1:0 ?>' == 1)
             $('#chk_request').trigger('click') 
    })
    $('[name="ref_code"]').keypress(function(e){
        if(e.which == 13){
             $('#chk_request').trigger('click')
             return false;
        }
    })
    $('#chk_request').click(function(){
        var ref_code = $('[name="ref_code"]').val()
        $('#msg').val('')
        $('#request_details').html('')
        $('[name="picked_up_by"]').parent().hide()
        $.ajax({
            url:'ajax.php?action=chk_request',
            method:'POST',
            data:{ref_code:ref_code,id:'<?php echo isset($id) ? $id : '' ?>'},
            success:function(resp){
                if(resp){
                    resp = JSON.parse(resp)
                    if(resp.status == 1){
                        var details = '<p><b>Patient Name:</b> '+resp.data.patient+'</p>'
                        details += '<p><b>Blood Group:</b> '+resp.data.blood_group+'</p>'
                        details += '<p><b>Volume Needed:</b> '+(resp.data.volumeL)+' L</p>'
                        details += '<p><b>Physician Name:</b> '+resp.data.physician_name+'</p>'
                        $('#request_details').html(details)
                        $('[name="picked_up_by"]').parent().show()
                    }else if(resp.status == 2){
                        $('#msg').html('<div class="alert alert-danger">Request is not yet approved.</div>')
                    }else if(resp.status == 3){
                        $('#msg').html('<div class="alert alert-danger">Request was already handed over.</div>')
                    }else{
                        $('#msg').html('<div class="alert alert-danger">Unknown Reference Code.</div>')
                    }
                }
            }
        })
    })
    $('#manage-handover').submit(function(e){
        e.preventDefault()
        start_load()
        $('#msg').html('')
        $.ajax({
            url:'ajax.php?action=save_handover',
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
