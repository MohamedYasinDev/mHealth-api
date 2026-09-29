<?php
session_start();
$host="localhost";$user="root";$pw="";$dbname="mobile_health";$port=3308;
$db=new mysqli($host,$user,$pw,$dbname,$port);
if($db->connect_error)die("DB Error");
$db->set_charset("utf8mb4");

// Login
$loginErr='';
if(isset($_POST['login'])){
    $e=trim($_POST['email']??'');$p=$_POST['password']??'';
    $st=$db->prepare("SELECT * FROM users WHERE email=? AND role='admin'");
    $st->bind_param("s",$e);$st->execute();$u=$st->get_result()->fetch_assoc();
    if($u&&password_verify($p,$u['password'])){$_SESSION['aid']=$u['id'];$_SESSION['aname']=$u['full_name'];}
    else $loginErr='Invalid credentials';
}
if(isset($_GET['logout'])){session_destroy();header("Location:index.php");exit;}

// CRUD Actions
$page=$_GET['page']??'dashboard';
$action=$_GET['action']??'';
$msg='';$msgType='';

if(isset($_SESSION['aid']) && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['crud_action'])){
    $ca=$_POST['crud_action'];
    $id=intval($_POST['id']??0);

    // ── USERS CRUD ──
    if($ca==='add_user'){
        $hp=password_hash($_POST['password'],PASSWORD_DEFAULT);
        $st=$db->prepare("INSERT INTO users(full_name,phone,email,password,role)VALUES(?,?,?,?,?)");
        $st->bind_param("sssss",$_POST['full_name'],$_POST['phone'],$_POST['email'],$hp,$_POST['role']);
        $st->execute()?$msg='User created':$msg='Error: '.$db->error;$msgType=$msg==='User created'?'success':'danger';
    }
    if($ca==='edit_user'){
        $sql="UPDATE users SET full_name=?,phone=?,email=?,role=? WHERE id=?";$params=[$_POST['full_name'],$_POST['phone'],$_POST['email'],$_POST['role'],$id];$types="ssssi";
        if(!empty($_POST['password'])){$sql="UPDATE users SET full_name=?,phone=?,email=?,role=?,password=? WHERE id=?";$hp=password_hash($_POST['password'],PASSWORD_DEFAULT);$params=[$_POST['full_name'],$_POST['phone'],$_POST['email'],$_POST['role'],$hp,$id];$types="sssssi";}
        $st=$db->prepare($sql);$st->bind_param($types,...$params);$st->execute()?$msg='User updated':$msg='Error: '.$db->error;$msgType='success';
    }
    if($ca==='delete_user'){$db->query("DELETE FROM users WHERE id=$id AND id!={$_SESSION['aid']}");$msg='User deleted';$msgType='success';}

    // ── HOSPITALS CRUD ──
    if($ca==='add_hospital'){
        $st=$db->prepare("INSERT INTO hospitals(name,type,phone,region,address)VALUES(?,?,?,?,?)");
        $st->bind_param("sssss",$_POST['name'],$_POST['type'],$_POST['phone'],$_POST['region'],$_POST['address']);
        $st->execute()?$msg='Hospital added':$msg='Error: '.$db->error;$msgType='success';
    }
    if($ca==='edit_hospital'){
        $st=$db->prepare("UPDATE hospitals SET name=?,type=?,phone=?,region=?,address=? WHERE id=?");
        $st->bind_param("sssssi",$_POST['name'],$_POST['type'],$_POST['phone'],$_POST['region'],$_POST['address'],$id);
        $st->execute()?$msg='Hospital updated':$msg='Error: '.$db->error;$msgType='success';
    }
    if($ca==='delete_hospital'){$db->query("DELETE FROM hospitals WHERE id=$id");$msg='Hospital deleted';$msgType='success';}

    // ── MOTHERS (users table) ──
    if($ca==='delete_mother'){$db->query("DELETE FROM users WHERE id=$id AND role='mother'");$msg='Mother deleted';$msgType='success';}

    // ── DOCTORS CRUD ──
    if($ca==='add_doctor'){
        $hp=password_hash($_POST['password']??'Doctor@123',PASSWORD_DEFAULT);$role=$_POST['specialization']==='Lab Technician'?'lab':'doctor';
        $st=$db->prepare("INSERT INTO users(full_name,phone,email,password,role)VALUES(?,?,?,?,?)");
        $st->bind_param("sssss",$_POST['full_name'],$_POST['phone'],$_POST['email'],$hp,$role);
        if($st->execute()){$uid=$db->insert_id;$st2=$db->prepare("INSERT INTO doctors(user_id,full_name,specialization,phone,email,hospital_id,is_active)VALUES(?,?,?,?,?,?,1)");
        $hid=intval($_POST['hospital_id'])?:null;$st2->bind_param("issssi",$uid,$_POST['full_name'],$_POST['specialization'],$_POST['phone'],$_POST['email'],$hid);$st2->execute();$msg='Doctor added';}
        else $msg='Error: '.$db->error;$msgType='success';
    }
    if($ca==='edit_doctor'){
        $st=$db->prepare("UPDATE doctors SET full_name=?,specialization=?,phone=?,email=?,hospital_id=? WHERE id=?");
        $hid=intval($_POST['hospital_id'])?:null;$st->bind_param("ssssii",$_POST['full_name'],$_POST['specialization'],$_POST['phone'],$_POST['email'],$hid,$id);
        $st->execute()?$msg='Doctor updated':$msg='Error: '.$db->error;$msgType='success';
    }
    if($ca==='delete_doctor'){$doc=$db->query("SELECT user_id FROM doctors WHERE id=$id")->fetch_assoc();if($doc){$db->query("DELETE FROM doctors WHERE id=$id");$db->query("DELETE FROM users WHERE id={$doc['user_id']}");}$msg='Doctor deleted';$msgType='success';}

    // ── APPOINTMENTS CRUD ──
    if($ca==='edit_appointment'){
        $st=$db->prepare("UPDATE appointments SET appointment_date=?,appointment_time=?,appointment_type=?,status=?,reason=? WHERE id=?");
        $st->bind_param("sssssi",$_POST['appointment_date'],$_POST['appointment_time'],$_POST['appointment_type'],$_POST['status'],$_POST['reason'],$id);
        $st->execute()?$msg='Appointment updated':$msg='Error';$msgType='success';
    }
    if($ca==='delete_appointment'){$db->query("DELETE FROM appointments WHERE id=$id");$msg='Appointment deleted';$msgType='success';}

    // ── HEALTH RECORDS CRUD ──
    if($ca==='edit_health'){
        $st=$db->prepare("UPDATE health_records SET weight=?,blood_pressure_systolic=?,blood_pressure_diastolic=?,fetal_heart_rate=?,risk_level=? WHERE id=?");
        $st->bind_param("diidsi",$_POST['weight'],$_POST['systolic'],$_POST['diastolic'],$_POST['fhr'],$_POST['risk_level'],$id);
        $st->execute()?$msg='Record updated':$msg='Error';$msgType='success';
    }
    if($ca==='delete_health'){$db->query("DELETE FROM health_records WHERE id=$id");$msg='Record deleted';$msgType='success';}

    // ── LAB TESTS CRUD ──
    if($ca==='edit_lab'){
        $st=$db->prepare("UPDATE lab_tests SET result=?,status=? WHERE id=?");
        $st->bind_param("ssi",$_POST['result'],$_POST['status'],$id);
        $st->execute()?$msg='Lab test updated':$msg='Error';$msgType='success';
    }
    if($ca==='delete_lab'){$db->query("DELETE FROM lab_tests WHERE id=$id");$msg='Lab test deleted';$msgType='success';}

    // ── MEDICATIONS CRUD ──
    if($ca==='edit_medication'){
        $st=$db->prepare("UPDATE medications SET medicine_name=?,dosage=?,frequency=?,instructions=?,is_active=? WHERE id=?");
        $active=isset($_POST['is_active'])?1:0;$st->bind_param("ssssii",$_POST['medicine_name'],$_POST['dosage'],$_POST['frequency'],$_POST['instructions'],$active,$id);
        $st->execute()?$msg='Medication updated':$msg='Error';$msgType='success';
    }
    if($ca==='delete_medication'){$db->query("DELETE FROM medications WHERE id=$id");$msg='Medication deleted';$msgType='success';}

    // ── NOTIFICATIONS ──
    if($ca==='delete_notification'){$db->query("DELETE FROM notifications WHERE id=$id");$msg='Notification deleted';$msgType='success';}

    // ── PREGNANCIES ──
    if($ca==='edit_pregnancy'){
        $st=$db->prepare("UPDATE pregnancies SET baby_count=?,pregnancy_status=?,expected_delivery_date=? WHERE id=?");
        $st->bind_param("issi",$_POST['baby_count'],$_POST['pregnancy_status'],$_POST['expected_delivery_date'],$id);
        $st->execute()?$msg='Pregnancy updated':$msg='Error';$msgType='success';
    }
    if($ca==='delete_pregnancy'){$db->query("DELETE FROM pregnancies WHERE id=$id");$msg='Pregnancy deleted';$msgType='success';}

    // ── DANGER SIGNS ──
    if($ca==='add_danger'){
        $st=$db->prepare("INSERT INTO danger_signs(trimester,title,description,emergency_action,is_active)VALUES(?,?,?,?,1)");
        $st->bind_param("ssss",$_POST['trimester'],$_POST['title'],$_POST['description'],$_POST['emergency_action']);
        $st->execute()?$msg='Danger sign added':$msg='Error';$msgType='success';
    }
    if($ca==='edit_danger'){
        $active=isset($_POST['is_active'])?1:0;
        $st=$db->prepare("UPDATE danger_signs SET trimester=?,title=?,description=?,emergency_action=?,is_active=? WHERE id=?");
        $st->bind_param("ssssii",$_POST['trimester'],$_POST['title'],$_POST['description'],$_POST['emergency_action'],$active,$id);
        $st->execute()?$msg='Danger sign updated':$msg='Error';$msgType='success';
    }
    if($ca==='delete_danger'){$db->query("DELETE FROM danger_signs WHERE id=$id");$msg='Danger sign deleted';$msgType='success';}

    if($msg && !headers_sent()){header("Location:index.php?page=$page&msg=".urlencode($msg)."&mt=$msgType");exit;}
}
$msg=$_GET['msg']??$msg;$msgType=$_GET['mt']??$msgType;

if(!isset($_SESSION['aid'])):
?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>body{background:url('images/bg_images.jpg') no-repeat center center fixed;background-size:cover;min-height:100vh;display:flex;align-items:center;justify-content:center}body::before{content:'';position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(26,35,126,0.6);z-index:0}.lc{background:rgba(255,255,255,0.95);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.4);max-width:400px;width:100%;overflow:hidden;position:relative;z-index:1;backdrop-filter:blur(10px)}.lh{background:linear-gradient(135deg,rgba(26,35,126,0.9),rgba(21,101,192,0.9));color:#fff;padding:2rem;text-align:center}.lb{padding:2rem}</style>
</head><body><div class="lc"><div class="lh"><i class="bi bi-heart-pulse" style="font-size:2.5rem"></i><h4 class="mt-2 mb-0">mHealth Admin</h4></div>
<div class="lb"><?php if($loginErr):?><div class="alert alert-danger py-2"><?=$loginErr?></div><?php endif;?>
<form method="POST"><div class="mb-3"><label class="form-label fw-semibold">Email</label><input type="email" name="email" class="form-control" required></div>
<div class="mb-3"><label class="form-label fw-semibold">Password</label><input type="password" name="password" class="form-control" required></div>
<button name="login" class="btn btn-primary w-100" style="background:linear-gradient(135deg,#1a237e,#1565c0);border:none;padding:.7rem;font-weight:600;border-radius:8px"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button></form></div></div></body></html>
<?php exit;endif;

function e($s){return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8');}
function fd($d){return $d?date('M d, Y',strtotime($d)):'—';}

// Stats
$s=[];
$s['mothers']=$db->query("SELECT COUNT(*) c FROM users WHERE role='mother'")->fetch_assoc()['c'];
$s['doctors']=$db->query("SELECT COUNT(*) c FROM doctors")->fetch_assoc()['c'];
$s['hospitals']=$db->query("SELECT COUNT(*) c FROM hospitals")->fetch_assoc()['c'];
$s['appointments']=$db->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'];
$s['pregnancies']=$db->query("SELECT COUNT(*) c FROM pregnancies")->fetch_assoc()['c'];
$s['lab_tests']=$db->query("SELECT COUNT(*) c FROM lab_tests")->fetch_assoc()['c'];
$s['medications']=$db->query("SELECT COUNT(*) c FROM medications WHERE is_active=1")->fetch_assoc()['c'];
$s['health_records']=$db->query("SELECT COUNT(*) c FROM health_records")->fetch_assoc()['c'];
$s['danger_signs']=$db->query("SELECT COUNT(*) c FROM danger_signs")->fetch_assoc()['c'];
$s['notifications']=$db->query("SELECT COUNT(*) c FROM notifications")->fetch_assoc()['c'];
$s['users']=$db->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$s['today_appts']=$db->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date=CURDATE()")->fetch_assoc()['c'];
$s['high_risk']=$db->query("SELECT COUNT(*) c FROM health_records WHERE risk_level='high'")->fetch_assoc()['c'];

$monthly=[];for($i=5;$i>=0;$i--){$m=date('Y-m',strtotime("-$i months"));$mn=date('M',strtotime("-$i months"));
$mc=$db->query("SELECT COUNT(*) c FROM users WHERE role='mother' AND DATE_FORMAT(created_at,'%Y-%m')='$m'")->fetch_assoc()['c'];
$ac=$db->query("SELECT COUNT(*) c FROM appointments WHERE DATE_FORMAT(created_at,'%Y-%m')='$m'")->fetch_assoc()['c'];
$monthly[]=['month'=>$mn,'mothers'=>(int)$mc,'appts'=>(int)$ac];}

$hospitals_list=$db->query("SELECT id,name FROM hospitals ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>mHealth Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>:root{--sb:250px;--pri:#1a237e;--pri2:#283593;--acc:#1565c0}body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5}.sidebar{position:fixed;top:0;left:0;width:var(--sb);height:100vh;background:linear-gradient(180deg,var(--pri),var(--pri2));color:#fff;overflow-y:auto;z-index:1040}.sidebar-brand{padding:1.2rem;text-align:center;border-bottom:1px solid rgba(255,255,255,.1)}.sidebar-nav .nav-link{color:rgba(255,255,255,.7);padding:.5rem 1rem;margin:2px 8px;border-radius:8px;display:flex;align-items:center;gap:10px;font-size:.85rem}.sidebar-nav .nav-link:hover,.sidebar-nav .nav-link.active{color:#fff;background:rgba(255,255,255,.15)}.sidebar-nav .nav-link.active{background:var(--acc)}.sidebar-nav .nav-link i{width:20px;text-align:center}.main{margin-left:var(--sb);min-height:100vh}.topbar{background:#fff;border-bottom:1px solid #e3e6ea;padding:.7rem 1.5rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:1030}.content{padding:1.5rem}.stat-card{border:none;border-radius:12px;transition:transform .2s}.stat-card:hover{transform:translateY(-3px)}.stat-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem}.table th{font-weight:600;font-size:.8rem;text-transform:uppercase;color:#6c757d}.modal-header{background:var(--pri);color:#fff}.modal-header .btn-close{filter:brightness(0) invert(1)}@media(max-width:991px){.sidebar{display:none}.main{margin-left:0}}</style>
</head><body>
<nav class="sidebar"><div class="sidebar-brand"><i class="bi bi-heart-pulse" style="font-size:1.5rem"></i><h6 class="mt-1 mb-0">mHealth Dashboard</h6><small class="opacity-50" style="font-size:.7rem">Admin Panel — Full CRUD</small></div>
<ul class="sidebar-nav nav flex-column mt-2">
<?php $nav=[['dashboard','bi-speedometer2','Dashboard'],['mothers','bi-people','Mothers'],['doctors','bi-hospital','Doctors'],['hospitals','bi-building','Hospitals'],['appointments','bi-calendar-check','Appointments'],['pregnancies','bi-balloon-heart','Pregnancies'],['health','bi-activity','Health Records'],['lab_tests','bi-droplet','Lab Tests'],['medications','bi-capsule','Medications'],['danger','bi-exclamation-triangle','Danger Signs'],['notifications','bi-bell','Notifications'],['users','bi-person-gear','All Users']];
foreach($nav as $n):?><li><a class="nav-link <?=$page===$n[0]?'active':''?>" href="?page=<?=$n[0]?>"><i class="bi <?=$n[1]?>"></i><?=$n[2]?></a></li><?php endforeach;?>
<li class="mt-2" style="border-top:1px solid rgba(255,255,255,.1);padding-top:8px"><a class="nav-link text-warning" href="?logout=1"><i class="bi bi-box-arrow-left"></i>Logout</a></li></ul></nav>

<div class="main"><div class="topbar"><h5 class="mb-0 fw-semibold"><?=ucfirst(str_replace('_',' ',$page))?></h5><div class="d-flex align-items-center gap-2"><span class="badge bg-primary"><?=e($_SESSION['aname'])?></span><a href="?logout=1" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-left"></i></a></div></div>
<div class="content">
<?php if($msg):?><div class="alert alert-<?=$msgType?:'info'?> alert-dismissible fade show"><?=e($msg)?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($page==='dashboard'):?>
<div class="row g-3 mb-4"><?php $cards=[['Mothers',$s['mothers'],'bi-people-fill','primary','mothers'],['Doctors',$s['doctors'],'bi-hospital-fill','success','doctors'],['Hospitals',$s['hospitals'],'bi-building-fill','info','hospitals'],['Appointments',$s['appointments'],'bi-calendar-check-fill','warning','appointments'],['Pregnancies',$s['pregnancies'],'bi-balloon-heart-fill','danger','pregnancies'],['Lab Tests',$s['lab_tests'],'bi-droplet-fill','secondary','lab_tests'],['Active Meds',$s['medications'],'bi-capsule','dark','medications'],['High Risk',$s['high_risk'],'bi-exclamation-triangle-fill','danger','health']];
foreach($cards as $c):?><div class="col-xl-3 col-md-4 col-6"><a href="?page=<?=$c[4]?>" class="text-decoration-none"><div class="card stat-card shadow-sm"><div class="card-body d-flex align-items-center gap-3 py-3"><div class="stat-icon bg-<?=$c[3]?> bg-opacity-10 text-<?=$c[3]?>"><i class="bi <?=$c[2]?>"></i></div><div><div class="text-muted small"><?=$c[0]?></div><div class="fs-4 fw-bold"><?=number_format($c[1])?></div></div></div></div></a></div><?php endforeach;?></div>
<div class="row g-3 mb-4"><div class="col-lg-8"><div class="card shadow-sm"><div class="card-header bg-white"><h6 class="fw-semibold mb-0"><i class="bi bi-bar-chart me-2"></i>Monthly Trends</h6></div><div class="card-body"><canvas id="chart" height="250"></canvas></div></div></div>
<div class="col-lg-4"><div class="card shadow-sm"><div class="card-header bg-white"><h6 class="fw-semibold mb-0">Quick Stats</h6></div><div class="card-body">
<?php foreach([["Today's Appts",$s['today_appts']],['Total Users',$s['users']],['Health Records',$s['health_records']],['Danger Signs',$s['danger_signs']],['Notifications',$s['notifications']]] as $q):?>
<div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted"><?=$q[0]?></span><span class="fw-bold"><?=$q[1]?></span></div><?php endforeach;?></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>new Chart(document.getElementById('chart'),{type:'bar',data:{labels:[<?php foreach($monthly as $m)echo"'".$m['month']."',";?>],datasets:[{label:'Mothers',data:[<?php foreach($monthly as $m)echo$m['mothers'].",";?>],backgroundColor:'rgba(233,30,99,.7)',borderRadius:6},{label:'Appointments',data:[<?php foreach($monthly as $m)echo$m['appts'].",";?>],backgroundColor:'rgba(21,101,192,.7)',borderRadius:6}]},options:{responsive:true,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true}}}});</script>

<?php else:
// ═══════════════════════════════════════════════════
// DATA QUERIES
// ═══════════════════════════════════════════════════
$queries=[
'mothers'=>"SELECT u.id,u.full_name,u.email,u.phone,m.blood_group,m.expected_delivery,u.created_at FROM users u LEFT JOIN mothers m ON m.user_id=u.id WHERE u.role='mother' ORDER BY u.created_at DESC",
'doctors'=>"SELECT d.id,d.full_name,d.specialization,d.phone,d.email,h.name AS hospital,d.is_active,d.user_id FROM doctors d LEFT JOIN hospitals h ON d.hospital_id=h.id ORDER BY d.created_at DESC",
'hospitals'=>"SELECT id,name,type,phone,region,address,created_at FROM hospitals ORDER BY name",
'appointments'=>"SELECT a.id,u.full_name AS patient,d.full_name AS doctor,h.name AS hospital,a.appointment_type,a.appointment_date,a.appointment_time,a.status,a.reason FROM appointments a LEFT JOIN users u ON a.user_id=u.id LEFT JOIN doctors d ON a.doctor_id=d.id LEFT JOIN hospitals h ON a.hospital_id=h.id ORDER BY a.appointment_date DESC",
'pregnancies'=>"SELECT p.id,COALESCE(u.full_name,u2.full_name,'Unknown') AS mother,p.baby_count,p.pregnancy_status,p.expected_delivery_date,p.pregnancy_number,p.created_at FROM pregnancies p LEFT JOIN mothers m ON p.mother_id=m.id LEFT JOIN users u ON m.user_id=u.id LEFT JOIN users u2 ON p.mother_id=u2.id ORDER BY p.created_at DESC",
'health'=>"SELECT hr.id,u.full_name AS patient,hr.weight,hr.blood_pressure_systolic AS systolic,hr.blood_pressure_diastolic AS diastolic,hr.fetal_heart_rate AS fhr,hr.risk_level,hr.record_date FROM health_records hr JOIN users u ON hr.user_id=u.id ORDER BY hr.record_date DESC",
'lab_tests'=>"SELECT lt.id,u.full_name AS patient,d.full_name AS doctor,lt.test_name,lt.test_date,lt.result,lt.status FROM lab_tests lt JOIN users u ON lt.user_id=u.id LEFT JOIN doctors d ON lt.doctor_id=d.id ORDER BY lt.test_date DESC",
'medications'=>"SELECT med.id,u.full_name AS patient,med.medicine_name,med.dosage,med.frequency,med.instructions,med.is_active FROM medications med JOIN users u ON med.user_id=u.id ORDER BY med.created_at DESC",
'danger'=>"SELECT id,trimester,title,description,emergency_action,is_active,created_at FROM danger_signs ORDER BY created_at DESC",
'notifications'=>"SELECT n.id,u.full_name AS user_name,n.title,n.type,n.is_read,n.created_at FROM notifications n JOIN users u ON n.user_id=u.id ORDER BY n.created_at DESC LIMIT 200",
'users'=>"SELECT id,full_name,email,phone,role,created_at FROM users ORDER BY created_at DESC",
];

if(isset($queries[$page])):
$result=$db->query($queries[$page]);
if(!$result){echo'<div class="alert alert-danger">SQL Error: '.e($db->error).'</div>';$rows=[];}
else $rows=$result->fetch_all(MYSQLI_ASSOC);
$cols=!empty($rows)?array_keys($rows[0]):[];

// Add button config
$addBtns=['users'=>'Add User','hospitals'=>'Add Hospital','doctors'=>'Add Doctor','danger'=>'Add Danger Sign'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?=count($rows)?> records</span>
    <?php if(isset($addBtns[$page])):?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i><?=$addBtns[$page]?></button>
    <?php endif;?>
</div>

<div class="card shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover table-striped mb-0 datatable"><thead class="table-light"><tr>
<?php foreach($cols as $c):if($c==='user_id')continue;?><th><?=ucfirst(str_replace('_',' ',$c))?></th><?php endforeach;?>
<th>Actions</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr>
<?php foreach($row as $k=>$v):if($k==='user_id')continue;$kl=strtolower($k);
if($kl==='status'||$kl==='pregnancy_status'):?><td><span class="badge bg-<?=$v==='completed'||$v==='normal'||$v==='ongoing'?'success':($v==='scheduled'||$v==='ordered'?'primary':($v==='cancelled'||$v==='missed'?'danger':'secondary'))?>"><?=e($v)?></span></td>
<?php elseif($kl==='risk_level'):?><td><span class="badge bg-<?=$v==='high'?'danger':($v==='medium'?'warning':'success')?>"><?=e(ucfirst($v??''))?></span></td>
<?php elseif($kl==='is_active'||$kl==='is_read'):?><td><?=$v?'<i class="bi bi-check-circle-fill text-success"></i>':'<i class="bi bi-x-circle text-danger"></i>'?></td>
<?php elseif($kl==='role'):?><td><span class="badge bg-<?=$v==='admin'?'primary':($v==='doctor'?'success':($v==='lab'?'info':'warning'))?>"><?=e(ucfirst($v))?></span></td>
<?php elseif(str_contains($kl,'date')||str_contains($kl,'created')):?><td class="small text-muted"><?=fd($v)?></td>
<?php else:?><td><?=e(mb_strimwidth($v??'—',0,50,'...'))?></td><?php endif;endforeach;?>
<td class="text-nowrap">
    <button class="btn btn-sm btn-outline-info" onclick='viewRow(<?=json_encode($row)?>)' title="View"><i class="bi bi-eye"></i></button>
    <button class="btn btn-sm btn-outline-primary" onclick='editRow(<?=json_encode($row)?>)' title="Edit"><i class="bi bi-pencil"></i></button>
    <button class="btn btn-sm btn-outline-danger" onclick='deleteRow(<?=$row["id"]?>)' title="Delete"><i class="bi bi-trash"></i></button>
</td></tr><?php endforeach;?>
<?php if(empty($rows)):?><tr><td colspan="<?=count($cols)+1?>" class="text-center py-4 text-muted">No records</td></tr><?php endif;?>
</tbody></table></div></div></div>

<!-- ═══ ADD MODALS ═══ -->
<?php if($page==='users'):?>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add User</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST"><input type="hidden" name="crud_action" value="add_user"><div class="modal-body">
<div class="mb-3"><label class="form-label">Full Name *</label><input name="full_name" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
<div class="mb-3"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required minlength="6"></div>
<div class="mb-3"><label class="form-label">Role *</label><select name="role" class="form-select"><option value="mother">Mother</option><option value="doctor">Doctor</option><option value="lab">Lab Technician</option><option value="admin">Admin</option></select></div>
</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div></form></div></div></div>
<?php endif;?>

<?php if($page==='hospitals'):?>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-building-add me-2"></i>Add Hospital</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST"><input type="hidden" name="crud_action" value="add_hospital"><div class="modal-body">
<div class="mb-3"><label class="form-label">Name *</label><input name="name" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Type</label><input name="type" class="form-control" placeholder="General, Maternity..."></div>
<div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
<div class="mb-3"><label class="form-label">Region</label><input name="region" class="form-control"></div>
<div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add Hospital</button></div></form></div></div></div>
<?php endif;?>

<?php if($page==='doctors'):?>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Doctor</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST"><input type="hidden" name="crud_action" value="add_doctor"><div class="modal-body">
<div class="mb-3"><label class="form-label">Full Name *</label><input name="full_name" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
<div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" placeholder="Default: Doctor@123"></div>
<div class="mb-3"><label class="form-label">Specialization *</label><select name="specialization" class="form-select">
<?php foreach(['Gynecologist','Obstetrician','Pediatrician','Cardiologist','General Physician','Lab Technician','Other'] as $sp):?><option><?=$sp?></option><?php endforeach;?></select></div>
<div class="mb-3"><label class="form-label">Hospital</label><select name="hospital_id" class="form-select"><option value="">— None —</option>
<?php foreach($hospitals_list as $h):?><option value="<?=$h['id']?>"><?=e($h['name'])?></option><?php endforeach;?></select></div>
</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add Doctor</button></div></form></div></div></div>
<?php endif;?>

<?php if($page==='danger'):?>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Add Danger Sign</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST"><input type="hidden" name="crud_action" value="add_danger"><div class="modal-body">
<div class="mb-3"><label class="form-label">Trimester</label><select name="trimester" class="form-select"><option>First</option><option>Second</option><option>Third</option><option>Any</option></select></div>
<div class="mb-3"><label class="form-label">Title *</label><input name="title" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
<div class="mb-3"><label class="form-label">Emergency Action</label><textarea name="emergency_action" class="form-control" rows="2"></textarea></div>
</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add</button></div></form></div></div></div>
<?php endif;?>

<!-- ═══ VIEW MODAL ═══ -->
<div class="modal fade" id="viewModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header" style="background:linear-gradient(135deg,#1a237e,#1565c0)"><h5 class="modal-title text-white"><i class="bi bi-eye me-2"></i>Record Details</h5><button class="btn-close" data-bs-dismiss="modal" style="filter:brightness(0) invert(1)"></button></div>
<div class="modal-body" id="viewContent"></div>
<div class="modal-footer">
<button class="btn btn-outline-secondary" onclick="printRecord()"><i class="bi bi-printer me-1"></i>Print</button>
<button class="btn btn-primary" onclick="editFromView()"><i class="bi bi-pencil me-1"></i>Edit</button>
<button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div></div></div></div>

<!-- ═══ EDIT MODAL (shared) ═══ -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Record</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST" id="editForm"><input type="hidden" name="id" id="edit_id">
<input type="hidden" name="crud_action" id="edit_action">
<div class="modal-body" id="editFields"></div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Changes</button></div></form></div></div></div>

<!-- ═══ DELETE FORM ═══ -->
<form method="POST" id="deleteForm" style="display:none"><input type="hidden" name="crud_action" id="del_action"><input type="hidden" name="id" id="del_id"></form>

<script>
const page='<?=$page?>';
const hospitals=<?=json_encode($hospitals_list)?>;

function editRow(r){
    let f='',act='edit_'+page;
    if(page==='users'){act='edit_user';f=`<div class="mb-3"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="${r.full_name||''}"></div><div class="mb-3"><label class="form-label">Email</label><input name="email" class="form-control" value="${r.email||''}"></div><div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="${r.phone||''}"></div><div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep"></div><div class="mb-3"><label class="form-label">Role</label><select name="role" class="form-select"><option value="mother" ${r.role==='mother'?'selected':''}>Mother</option><option value="doctor" ${r.role==='doctor'?'selected':''}>Doctor</option><option value="lab" ${r.role==='lab'?'selected':''}>Lab</option><option value="admin" ${r.role==='admin'?'selected':''}>Admin</option></select></div>`;}
    else if(page==='hospitals'){act='edit_hospital';f=`<div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="${r.name||''}"></div><div class="mb-3"><label class="form-label">Type</label><input name="type" class="form-control" value="${r.type||''}"></div><div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="${r.phone||''}"></div><div class="mb-3"><label class="form-label">Region</label><input name="region" class="form-control" value="${r.region||''}"></div><div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control">${r.address||''}</textarea></div>`;}
    else if(page==='doctors'){act='edit_doctor';let hOpts=hospitals.map(h=>`<option value="${h.id}" ${r.hospital_id==h.id?'selected':''}>${h.name}</option>`).join('');f=`<div class="mb-3"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="${r.full_name||''}"></div><div class="mb-3"><label class="form-label">Specialization</label><input name="specialization" class="form-control" value="${r.specialization||''}"></div><div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="${r.phone||''}"></div><div class="mb-3"><label class="form-label">Email</label><input name="email" class="form-control" value="${r.email||''}"></div><div class="mb-3"><label class="form-label">Hospital</label><select name="hospital_id" class="form-select"><option value="">—</option>${hOpts}</select></div>`;}
    else if(page==='appointments'){act='edit_appointment';f=`<div class="mb-3"><label class="form-label">Date</label><input type="date" name="appointment_date" class="form-control" value="${r.appointment_date||''}"></div><div class="mb-3"><label class="form-label">Time</label><input type="time" name="appointment_time" class="form-control" value="${r.appointment_time||''}"></div><div class="mb-3"><label class="form-label">Type</label><input name="appointment_type" class="form-control" value="${r.appointment_type||''}"></div><div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option ${r.status==='scheduled'?'selected':''}>scheduled</option><option ${r.status==='completed'?'selected':''}>completed</option><option ${r.status==='cancelled'?'selected':''}>cancelled</option><option ${r.status==='missed'?'selected':''}>missed</option></select></div><div class="mb-3"><label class="form-label">Reason</label><textarea name="reason" class="form-control">${r.reason||''}</textarea></div>`;}
    else if(page==='health'){act='edit_health';f=`<div class="mb-3"><label class="form-label">Weight (kg)</label><input type="number" step="0.1" name="weight" class="form-control" value="${r.weight||''}"></div><div class="mb-3"><label class="form-label">BP Systolic</label><input type="number" name="systolic" class="form-control" value="${r.systolic||''}"></div><div class="mb-3"><label class="form-label">BP Diastolic</label><input type="number" name="diastolic" class="form-control" value="${r.diastolic||''}"></div><div class="mb-3"><label class="form-label">Fetal Heart Rate</label><input type="number" name="fhr" class="form-control" value="${r.fhr||''}"></div><div class="mb-3"><label class="form-label">Risk Level</label><select name="risk_level" class="form-select"><option ${r.risk_level==='low'?'selected':''}>low</option><option ${r.risk_level==='medium'?'selected':''}>medium</option><option ${r.risk_level==='high'?'selected':''}>high</option></select></div>`;}
    else if(page==='lab_tests'){act='edit_lab';f=`<div class="mb-3"><label class="form-label">Result</label><textarea name="result" class="form-control">${r.result||''}</textarea></div><div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option ${r.status==='ordered'?'selected':''}>ordered</option><option ${r.status==='completed'?'selected':''}>completed</option></select></div>`;}
    else if(page==='medications'){act='edit_medication';f=`<div class="mb-3"><label class="form-label">Medicine</label><input name="medicine_name" class="form-control" value="${r.medicine_name||''}"></div><div class="mb-3"><label class="form-label">Dosage</label><input name="dosage" class="form-control" value="${r.dosage||''}"></div><div class="mb-3"><label class="form-label">Frequency</label><input name="frequency" class="form-control" value="${r.frequency||''}"></div><div class="mb-3"><label class="form-label">Instructions</label><textarea name="instructions" class="form-control">${r.instructions||''}</textarea></div><div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" ${r.is_active==1?'checked':''}><label class="form-check-label">Active</label></div>`;}
    else if(page==='pregnancies'){act='edit_pregnancy';f=`<div class="mb-3"><label class="form-label">Baby Count</label><input type="number" name="baby_count" class="form-control" value="${r.baby_count||1}" min="1" max="3"></div><div class="mb-3"><label class="form-label">Status</label><select name="pregnancy_status" class="form-select"><option ${r.pregnancy_status==='ongoing'?'selected':''}>ongoing</option><option ${r.pregnancy_status==='completed'?'selected':''}>completed</option></select></div><div class="mb-3"><label class="form-label">Expected Delivery</label><input type="date" name="expected_delivery_date" class="form-control" value="${r.expected_delivery_date||''}"></div>`;}
    else if(page==='danger'){act='edit_danger';f=`<div class="mb-3"><label class="form-label">Trimester</label><select name="trimester" class="form-select"><option ${r.trimester==='First'?'selected':''}>First</option><option ${r.trimester==='Second'?'selected':''}>Second</option><option ${r.trimester==='Third'?'selected':''}>Third</option><option ${r.trimester==='Any'?'selected':''}>Any</option></select></div><div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" value="${r.title||''}"></div><div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control">${r.description||''}</textarea></div><div class="mb-3"><label class="form-label">Emergency Action</label><textarea name="emergency_action" class="form-control">${r.emergency_action||''}</textarea></div><div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" ${r.is_active==1?'checked':''}><label class="form-check-label">Active</label></div>`;}
    else{f='<p class="text-muted">Edit not available for this module</p>';}
    document.getElementById('edit_id').value=r.id;
    document.getElementById('edit_action').value=act;
    document.getElementById('editFields').innerHTML=f;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteRow(id){
    if(!confirm('Are you sure you want to delete this record?'))return;
    const acts={users:'delete_user',hospitals:'delete_hospital',doctors:'delete_doctor',appointments:'delete_appointment',health:'delete_health',lab_tests:'delete_lab',medications:'delete_medication',notifications:'delete_notification',pregnancies:'delete_pregnancy',danger:'delete_danger',mothers:'delete_mother'};
    document.getElementById('del_id').value=id;
    document.getElementById('del_action').value=acts[page]||'delete_'+page;
    document.getElementById('deleteForm').submit();
}

let currentViewRow=null;
function viewRow(r){
    currentViewRow=r;
    let html='<div class="row g-3" id="printArea">';
    html+='<div class="col-12 text-center mb-3 border-bottom pb-3"><h4 class="fw-bold text-primary mb-1">mHealth System</h4><small class="text-muted">Record Details — '+page.replace('_',' ').toUpperCase()+'</small></div>';
    for(let key in r){
        if(key==='user_id')continue;
        let label=key.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
        let val=r[key]??'—';
        let badge='';
        if(key==='status'||key==='pregnancy_status'){let color=val==='completed'||val==='ongoing'||val==='normal'?'success':(val==='scheduled'||val==='ordered'?'primary':(val==='cancelled'||val==='missed'?'danger':'secondary'));badge=`<span class="badge bg-${color} fs-6">${val}</span>`;}
        else if(key==='risk_level'){let color=val==='high'?'danger':(val==='medium'?'warning':'success');badge=`<span class="badge bg-${color} fs-6">${val}</span>`;}
        else if(key==='role'){let color=val==='admin'?'primary':(val==='doctor'?'success':(val==='lab'?'info':'warning'));badge=`<span class="badge bg-${color} fs-6">${val}</span>`;}
        else if(key==='is_active'||key==='is_read'){badge=val==1?'<i class="bi bi-check-circle-fill text-success fs-5"></i> Active':'<i class="bi bi-x-circle-fill text-danger fs-5"></i> Inactive';}
        html+=`<div class="col-md-6"><div class="p-3 rounded-3" style="background:#f8f9fa;border-left:4px solid #1a237e"><small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:1px">${label}</small><div class="fw-semibold mt-1">${badge||val}</div></div></div>`;
    }
    html+='</div>';
    document.getElementById('viewContent').innerHTML=html;
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}
function editFromView(){bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();if(currentViewRow)editRow(currentViewRow);}
function printRecord(){
    const content=document.getElementById('printArea').innerHTML;
    const win=window.open('','_blank');
    win.document.write(`<!DOCTYPE html><html><head><title>Print Record</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"><style>body{font-family:'Segoe UI',sans-serif;padding:30px;max-width:800px;margin:auto}@media print{.no-print{display:none}}.header{text-align:center;border-bottom:3px solid #1a237e;padding-bottom:15px;margin-bottom:20px}.header h2{color:#1a237e;margin:0}.footer{text-align:center;margin-top:30px;padding-top:15px;border-top:1px solid #ddd;color:#999;font-size:12px}</style></head><body><div class="header"><h2>mHealth — Maternal Healthcare System</h2><small>Official Record — Printed ${new Date().toLocaleDateString()}</small></div><div class="row g-3">${content}</div><div class="footer">System-generated document • mHealth Admin Dashboard • ${new Date().toLocaleString()}</div><div class="text-center mt-3 no-print"><button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Print</button></div></body></html>`);
    win.document.close();
}

// ── INPUT VALIDATION ──
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('input[name="full_name"],input[name="name"],input[name="title"]').forEach(el=>{el.setAttribute('pattern','[A-Za-z\\s]+');el.setAttribute('title','Letters only');el.addEventListener('input',function(){this.value=this.value.replace(/[^A-Za-z\s]/g,'');});});
    document.querySelectorAll('input[name="phone"]').forEach(el=>{el.setAttribute('pattern','[0-9]{7,15}');el.setAttribute('title','Numbers only — 7 to 15 digits');el.setAttribute('type','tel');el.addEventListener('input',function(){this.value=this.value.replace(/[^0-9]/g,'');});});
    document.querySelectorAll('input[name="password"]').forEach(el=>{el.setAttribute('minlength','6');el.setAttribute('title','Minimum 6 characters');});
    document.querySelectorAll('input[name="email"]').forEach(el=>{el.setAttribute('type','email');el.setAttribute('title','Enter valid email');el.addEventListener('input',function(){this.value=this.value.replace(/\s/g,'');});});
    document.querySelectorAll('.modal-body input,.modal-body select,.modal-body textarea').forEach(el=>{if(el.name!=='password'||el.closest('#addModal')){el.setAttribute('required','true');}});
    document.querySelectorAll('form').forEach(form=>{form.addEventListener('submit',function(e){
        const email=form.querySelector('input[name="email"]');if(email&&email.value){if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)){e.preventDefault();alert('Please enter a valid email');email.focus();return;}}
        const pw=form.querySelector('input[name="password"]');if(pw&&pw.value&&pw.value.length<6&&pw.hasAttribute('required')){e.preventDefault();alert('Password must be at least 6 characters');pw.focus();return;}
    });});
});
</script>

<?php else:?><div class="alert alert-warning">Page not found. <a href="?page=dashboard">Dashboard</a></div><?php endif;endif;?>
</div></div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>$(document).ready(function(){$('.datatable').DataTable({pageLength:15,responsive:true,language:{search:'',searchPlaceholder:'Search...'}});});</script>
</body></html><?php $db->close();?>