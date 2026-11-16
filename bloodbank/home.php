<?php include 'db_connect.php' ?>
<style>
   span.float-right.summary_icon {
    font-size: 3rem;
    position: absolute;
    right: 1rem;
    top: 0;
}
.imgs{
		margin: .5em;
		max-width: calc(100%);
		max-height: calc(100%);
	}
	.imgs img{
		max-width: calc(100%);
		max-height: calc(100%);
		cursor: pointer;
	}
	#imagesCarousel,#imagesCarousel .carousel-inner,#imagesCarousel .carousel-item{
		height: 60vh !important;background: black;
	}
	#imagesCarousel .carousel-item.active{
		display: flex !important;
	}
	#imagesCarousel .carousel-item-next{
		display: flex !important;
	}
	#imagesCarousel .carousel-item img{
		margin: auto;
	}
	#imagesCarousel img{
		width: auto!important;
		height: auto!important;
		max-height: calc(100%)!important;
		max-width: calc(100%)!important;
	}
</style>

<div class="container-fluid">
	<div class="row mt-3 ml-3 mr-3">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <?php echo "Welcome back ". htmlspecialchars(isset($_SESSION['login_name']) ? $_SESSION['login_name'] : 'User')."!"  ?>
                    <hr>
                    <h4><b>Available Blood per group in Liters</b></h4>
                    <div class="row">
                        <?php 
                        $blood_group = array("A+","B+","O+","AB+","A-","B-","O-","AB-");
                        $bg_inn = array();
                        $bg_out = array();
                        foreach($blood_group as $v){
                            $bg_inn[$v] = 0; 
                            $bg_out[$v] = 0; 
                        }
                        if (isset($conn) && $conn instanceof mysqli && empty($conn->connect_error)) {
                            $qry = $conn->query("SELECT * FROM blood_inventory ");
                            if ($qry && $qry instanceof mysqli_result) {
                                while($row = $qry->fetch_assoc()){
                                    if(isset($row['blood_group'])){
                                        if($row['status'] == 1){
                                            $bg_inn[$row['blood_group']] = ($bg_inn[$row['blood_group']] ?? 0) + (float)$row['volume'];
                                        }else{
                                            $bg_out[$row['blood_group']] = ($bg_out[$row['blood_group']] ?? 0) + (float)$row['volume'];
                                        }
                                    }
                                }
                            }
                        }
                        ?>
                        <?php foreach ($blood_group as $v): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <div class="card-body text-dark">
                                        <span class="float-right summary_icon"> <?php echo $v ?> <i class="fa fa-tint text-danger"></i></span>
                                        <h4><b>
                                            <?php echo max(0, (($bg_inn[$v] ?? 0) - ($bg_out[$v] ?? 0)) / 1000); ?>
                                        </b></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>	
                    <hr>
                    <div class="row">
                        <?php
                        $total_donors = 0;
                        $total_donated_today = 0;
                        $today_requests = 0;
                        $today_approved = 0;

                        if (isset($conn) && $conn instanceof mysqli && empty($conn->connect_error)) {
                            $q_donors = $conn->query("SELECT * FROM donors");
                            if ($q_donors && $q_donors instanceof mysqli_result) {
                                $total_donors = $q_donors->num_rows;
                            }
                            $q_donated = $conn->query("SELECT * FROM blood_inventory where status = 1 and date(date_created) = '".date('Y-m-d')."' ");
                            if ($q_donated && $q_donated instanceof mysqli_result) {
                                $total_donated_today = $q_donated->num_rows;
                            }
                            $q_req = $conn->query("SELECT * FROM requests where date(date_created) = '".date('Y-m-d')."' ");
                            if ($q_req && $q_req instanceof mysqli_result) {
                                $today_requests = $q_req->num_rows;
                            }
                            $q_app = $conn->query("SELECT * FROM requests where date(date_created) = '".date('Y-m-d')."' and status = 1 ");
                            if ($q_app && $q_app instanceof mysqli_result) {
                                $today_approved = $q_app->num_rows;
                            }
                        }
                        ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <div class="card-body text-dark">
                                        <span class="float-right summary_icon"> <i class="fa fa-user-friends text-primary "></i></span>
                                        <h4><b>
                                            <?php echo $total_donors ?>
                                        </b></h4>
                                        <p><b>Total Donors</b></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <div class="card-body text-dark">
                                        <span class="float-right summary_icon"> <i class="fa fa-notes-medical text-danger "></i></span>
                                        <h4><b>
                                            <?php echo $total_donated_today ?>
                                        </b></h4>
                                        <p><b>Total Donated Today</b></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <div class="card-body text-dark">
                                        <span class="float-right summary_icon"> <i class="fa fa-th-list "></i></span>
                                        <h4><b>
                                            <?php echo $today_requests ?>
                                        </b></h4>
                                        <p><b>Today's Requests</b></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <div class="card-body text-dark">
                                        <span class="float-right summary_icon"> <i class="fa fa-check text-primary "></i></span>
                                        <h4><b>
                                            <?php echo $today_approved ?>
                                        </b></h4>
                                        <p><b>Today's Approved Requests</b></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>      			
        </div>
    </div>
</div>
<script>
	$('#manage-records').submit(function(e){
        e.preventDefault()
        start_load()
        $.ajax({
            url:'ajax.php?action=save_track',
            data: new FormData($(this)[0]),
            cache: false,
            contentType: false,
            processData: false,
            method: 'POST',
            type: 'POST',
            success:function(resp){
                var resp = JSON.parse(resp)
                if(resp.status == 1){
                    alert_toast("Data successfully saved",'success')
                    setTimeout(function(){
                        location.reload()
                    },800)
                }
            }
        })
    })
</script>
