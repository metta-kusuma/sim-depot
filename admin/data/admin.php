<style>
td
{
font-size:12px;
border:1px #a9c6c9 solid;
color:black;
}

th
{
font-size:12px;
border:1px #a9c6c9 solid;
color:black;
}
 
 input[type="text"], input[type="date"], input[type="number"],  input[type="password"], select, textarea {
	font-family:Tahoma, Geneva, sans-serif;
	font-size:11px;
	padding:5px;
	 
	height:30px;
	border:1px solid #CCC;
}
 
</style>
 
<!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-10">
          <div class="col-sm-2">
            <h1 class="m-0 text-dark">DATA USER </h1>
          </div><!-- /.col -->
          <div class="col-sm-10">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="index.php">Home</a></li>
              <li class="breadcrumb-item active"><a href='index.php?menu=ongkir&act=tambah'>User</a></li>
            </ol>       
			 <div class='col-12'>  
<?php
 
 

switch($_GET[act]){
    default:
    echo " 
		  
           <input type=button value='Tambah' onclick=location.href='?menu=admin&act=tambah'>
		  
     <table width=50% STYLE=BORDER-COLLAPSE:COLLAPSE; border=0>
         <table class=table width=50%>
           
		  <th height=30>NO
		  <th>USER LOGIN
		  <th>NAMA USER
		  <th>LEVEL
			  
		  <th><center>AKSI</th></tr>";
    
    $tampil = mysqli_query($conn,"SELECT * FROM user ORDER BY nama ASC  ");
    while($r=mysqli_fetch_array($tampil))
		{
		$no=$no+1;
         echo "<tr class='td' bgcolor='#FFF'><td>$no</td>
         <td>$r[user_login]
		 <td>$r[nama]
		 <td>$r[level]
		 
		 
         <td><a href=?menu=admin&act=edit&user_login=$r[user_login]><center>Edit</a> | 
 	     <a href=\"./data.php?menu=admin&act=batal&user_login=$r[user_login]\"onClick=\"return confirm('Apakah (&nbsp;&nbsp;$r[nama]&nbsp;&nbsp;) Di Hapus??')\">Hapus		
				</tr>";
		 }
    echo "</table><br><br><br><br>";
    break;
  
  case "tambah":
       echo "<br><br><center> &nbsp;<B>TAMBAH USER</B>
          <form method=POST action='./data.php?menu=admin&act=input' encadmin='multipart/form-data'>
          <table width=50% STYLE=BORDER-COLLAPSE:COLLAPSE; border=0>
         <table class=table width=50%>
          <tr class='td' bgcolor='#FFF'><td><font size=2 face=ARIAL>User Login        <td>  <input type=text name='user_login' size=15 required/> 
          
		  <tr class='td' bgcolor='#FFF'><td><font size=2 face=ARIAL>Password        <td>  <input type=text name='password' size=15 required/> 
          
		  <tr class='td' bgcolor='#FFF'><td><font size=2 face=ARIAL>Nama User    <td>  <input type=text name='nama' size=30	required/> 
		  <tr class='td' bgcolor='#FFF'><td><font size=2 face=ARIAL>Level    <td><select name=level>
																				 <option value=Admin>Admin
																				 <option value=Direktur>Direktur
																				 <option value=Kurir>Kurir
																				 </select>

          <tr class='td' bgcolor='#FFF'><td><td colspan=2>
		  <input type=submit value=Simpan>
          <input type=submit value=Batal onclick=self.history.back()></td></tr>
          </table><br><br><br><br><br><br></form>";
          break;
    
  case "edit":
           $edit = mysql_query("SELECT * FROM admin WHERE user_login='$_GET[user_login]'");
           $r    = mysql_fetch_array($edit);
       echo "<br><br><center><B>EDIT DATA USER</B>
	       <form method=POST encadmin='multipart/form-data' action=./data.php?menu=admin&act=update>
          <input type=hidden name=user_login value=$r[user_login]>
           <table width=50% STYLE=BORDER-COLLAPSE:COLLAPSE; border=0>
         <table class=table width=50%>
		<tr class='td' bgcolor='#FFF'><td><font size=2 face=ARIAL>User Login    <td>  <input type=text name='user_login1' size=15 value=$r[user_login] required/> 
	    <tr class='td' bgcolor='#FFF'><td><font size=2 face=ARIAL>Password        <td>  <input type=text name='password' size=15 value='$r[password]' required/> 
          	  <tr class='td' bgcolor='#FFF'><td><font size=2 face=ARIAL>Nama User    <td>  <input type=text name='nama' size=30	value='$r[nama]' required/> 
	
     		  <tr class='td' bgcolor='#FFF'><td><font size=2 face=ARIAL>Level    <td><select name=level>
																				 <option value=$r[level] selected>$r[level]
																				 <option value=Admin>Admin
																				 <option value=Direktur>Direktur
																				 																				 <option value=Kurir>Kurir

																				 </select>
     
	           <tr class='td' bgcolor='#FFF'><td><td colspan=2><input type=submit value=Update>
          <input type=submit value=Batal onclick=self.history.back()></td></tr>
          </table></form><br><br><br><br>";
          break;  
}
?>
