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
 
input[type="text"], input[type="date"], input[type="number"], input[type="password"], select, textarea {
    font-family:Tahoma, Geneva, sans-serif;
    font-size:11px;
    padding:5px;
    height:30px;
    border:1px solid #CCC;
}
 
</style>
 
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-10">
            <div class="col-sm-2">
                <h1 class="m-0 text-dark">DATA FILTER </h1>
            </div><div class="col-sm-10">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active"><a href='index.php?menu=datafilter&act=tambah'>Data Filter</a></li>
                </ol>
            </div>
        </div>
    </div>
</div>
 
<div class='col-12'> 
 
<?php

$act = isset($_GET['act']) ? $_GET['act'] : '';

switch($act) {
    default:
    echo "<br><CENTER><b>DATA FILTER</b><br>";
    echo "<input type=button value='Tambah' onclick=location.href='index.php?menu=datafilter&act=tambah'>";
    echo "<table id='example1' class='table table-bordered table-striped'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th height=30>ID Filter</th>";
    echo "<th>Nama Filter</th>";
    echo "<th>Deskripsi</th>";
    echo "<th><center>AKSI</center></th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>"; 
 
    $tampil = mysqli_query($conn,"SELECT * FROM data_filter ORDER BY id_filter");
    while($r = mysqli_fetch_array($tampil)) {
        echo "<tr class='td' bgcolor='#FFF'>";
        echo "<td>".htmlspecialchars($r['id_filter'])."</td>";
        echo "<td>".htmlspecialchars($r['nama_filter'])."</td>";
        echo "<td>".htmlspecialchars($r['deskripsi'])."</td>";
        echo "<td><center>";
        $id_edit = mysqli_real_escape_string($conn, $r['id_filter']);
        $id_hapus = mysqli_real_escape_string($conn, $r['id_filter']);
        $nama_hapus = htmlspecialchars($r['nama_filter']);
        
        echo "<a href='index.php?menu=datafilter&act=edit&id_filter=".$id_edit."'><center>Edit</a> | ";
        echo "<a href=\"./data.php?menu=datafilter&act=batal&id_filter=".$id_hapus."\" onClick=\"return confirm('Apakah (&nbsp;&nbsp;".$nama_hapus."&nbsp;&nbsp;) Di Hapus??')\">Hapus</a>";
        echo "</center></td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table><br><br><br><br>";
    break;
 
    case "tambah":
        echo "<br><br><center> &nbsp;<b>TAMBAH FILTER</b></center>";
        echo "<form method=POST action='./data.php?menu=datafilter&act=input' enctype='multipart/form-data'>";
        echo "<table class='table' width='50%'>";
        echo "<tr class='td' bgcolor='#FFF'>";
        echo "<td><font size=2 face=ARIAL>Nama Filter</font></td>";
        echo "<td><input type='text' name='nama_filter' style='width: 200px; height: 34px' autocomplete='off' required/></td>";
        echo "</tr>";
        echo "<tr class='td' bgcolor='#FFF'>";
        echo "<td><font size=2 face=ARIAL>Deskripsi</font></td>";
        echo "<td><input type='text' name='deskripsi' style='width: 200px; height: 34px' autocomplete='off' required/></td>";
        echo "</tr>";
        echo "<tr class='td' bgcolor='#FFF'>";
        echo "<td colspan='2'><input type='submit' value='Simpan'>";
        echo "<input type='button' value='Batal' onclick='self.history.back()'></td>";
        echo "</tr>";
        echo "</table>";
        echo "</form><br><br><br><br><br><br>";
        break;
 
    case "edit":
        $id_edit = mysqli_real_escape_string($conn, $_GET['id_filter']);
        $edit = mysqli_query($conn,"SELECT * FROM data_filter WHERE id_filter='$id_edit'");
        $r = mysqli_fetch_array($edit);
 
        echo "<br><br><center><b>EDIT FILTER</b></center>";
        echo "<form method=POST enctype='multipart/form-data' action='./data.php?menu=datafilter&act=update'>";
        echo "<input type=hidden name='id_filter' value='".htmlspecialchars($r['id_filter'])."'>";
        echo "<table class='table' width='50%'>";
        echo "<tr class='td' bgcolor='#FFF'>";
        echo "<td><font size=2 face=ARIAL>Nama Filter</font></td>";
        echo "<td><input type='text' name='nama_filter' value='".htmlspecialchars($r['nama_filter'])."' style='width: 200px; height: 24px' autocomplete='off' required/></td>";
        echo "</tr>";
        echo "<tr class='td' bgcolor='#FFF'>";
        echo "<td><font size=2 face=ARIAL>Deskripsi</font></td>";
        echo "<td><input type='text' name='deskripsi' value='".htmlspecialchars($r['deskripsi'])."' style='width: 200px; height: 24px' autocomplete='off' required/></td>";
        echo "</tr>";
        echo "<tr class='td' bgcolor='#FFF'>";
        echo "<td colspan='2'><input type='submit' value='Update'>";
        echo "<input type='button' value='Batal' onclick='self.history.back()'></td>";
        echo "</tr>";
        echo "</table></form><br><br><br><br>";
        break; 
}
?>