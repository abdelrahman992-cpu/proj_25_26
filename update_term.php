<html  dir="rtl">
<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
<title>تحديث مصطلح </title>

</head>

<body>

 <?php
 include("conn.php");
include("header.php");
?>
  <h1><p  > تعديل مصطلح </p>     </h1>

<?php
include("post.php");
    include("validation.php");
 mysqli_query($connect,"SET NAMES 'utf8'");
$sql="select * from terms";
$query=mysqli_query($connect,$sql);
$num=mysqli_num_rows($query);
?>
<form method="post" action="update_term.php">
<?php
echo("<h1>عدد المصطلحات $num </h1>");
?>

<table class="table table-dark">
	<tr>
		<td>المسلسل</td>
		<td>المصطلح</td>
		<td>الترجمة</td>
		<td>التعريف</td>
		<td>الصورة</td>
		<td>الخصائص</td>
	</tr>
	<?php
		while($row = mysqli_fetch_array($query))
		{
		$id=$row['id'];
		$term=$row['term'];
		$trans=$row['trans'];
		$defe=$row['defe'];
		$picture=$row['picture'];
			echo ("
				<tr >
		<td>$id</td>
		<td>$term</td>
		<td>$trans</td>
		<td>$defe</td>
		<td>  <input name='termp' type='image' src='$picture' width='80' height='80'  /></td>
		<td> <a href='update_term.php?id=$id'>تعديل</a>   </td>
	</tr>


			");
	}

		?>
    </table>
<?php
//$id=$_GET['id'];
if(isset($_GET['id']))
{


//if($_GET['action']=='edit'){
$sql="select * from terms where id='$_GET[id]'";
$query=mysqli_query($connect,$sql);
$row=mysqli_fetch_array($query);
$id=$row['id'];
		$term=$row['term'];
		$trans=$row['trans'];
		$defe=$row['defe'];
		$picture=$row['picture'];


/////
?>
 </form>

<form method='post' action='update_term.php?id=$_GET[id]&action=$_GET[action]' enctype="multipart/form-data">
<hr  style='color:orange:maroon;width:1267px'/>
<h2>تعديل بيانات </h2>
<hr  style='color:orange:maroon;width:1267px'/>

 <div >
 <?php  
        echo(" 
  		
		<br />
        <input name='iddata' type='hidden' style='width: 482px' value='$id'required />
		المصطلح&nbsp;&nbsp;&nbsp;
		<input name='txt_term' type='text' style='width: 482px' value='$term' required/> <br />
		<br />
		ترجمتة&nbsp;&nbsp;&nbsp;
		<input name='trans' type='text' style='width: 482px' value='$trans'required /> <br />
		<br />
		تعريف&nbsp;&nbsp;&nbsp;
		<textarea name='TextArea1' style='width: 480px; height: 30px' required>$defe</textarea> <br />
		<br />
	االصورة&nbsp;&nbsp;
                 <input name='termp' type='image' src='$picture' width='80' height='80' required />
                 <input name='pic' type='hidden' style='width: 482px' value='$picture' />
           <input name='filedata' type='file' maxlength='43'>
        <br /><br /><br /><br />
		<input name='Submit2' style='width: 76px' type='submit' value='إضافة' /><br />
		<br />
	 ");	?>
<?php

if(isset($_POST['Submit2'])){

 if(!is_dir('up')){
      mkdir('up');
    }
     $fileName = $_FILES['filedata']['name'];
    $tmpName  = $_FILES['filedata']['tmp_name'];
   if(!empty($fileName)){
        move_uploaded_file($tmpName,'up/'.$fileName);
    }
    $iddata=sanStr($_POST['iddata']);
$terma=sanStr($_POST['txt_term']);
$transa=sanStr($_POST['trans']);
$defea=sanStr($_POST['TextArea1']);
$pic=sanStr($_POST['pic']);
    if(!empty($fileName)){
       $picturea="up/" . $fileName . " ";
    }
    else{
    $picturea= $pic ;
    }

$sql="update terms set term='$terma',trans='$transa'   ,defe='$defea'    ,picture='$picturea'      where id='$iddata' ";
$query=mysqli_query($connect,$sql);
if($query){
 header("Location: update_term.php");
 exit;
}
}
}
?>
</div>
 </form>
      <?
    include('footer.php');
    ?>

</body>

</html>
