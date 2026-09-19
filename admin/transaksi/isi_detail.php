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
            <h1 class="m-0 text-dark">DATA ONGKIR </h1>
          </div><!-- /.col -->
          <div class="col-sm-10">
            <ol class="breadcrumb float-sm-right">
               </ol>       
			 <div class='col-12'> 
<?php
   $no_order=$_GET[kd_pesan];
  echo "<br><br><center><span class=title2><b>KERANJANG BELANJA ANDA</b>
    <a href=?menu=pemesanan>  
	<font color=red><blink>Kembali</b></font></a> 
 <center>
  <table width=80% STYLE=BORDER-COLLAPSE:COLLAPSE; border=1 bgcolor=#333333>
         <table class=table width=80%>
          <tr class=th>	
 <div id=atas> 
 <td>No  <td>Kode  <td>Nama menu         <td>Jumlah   <td>Harga   <td>Total  
 ";
 $no=0;
				$tampil=mysqli_query($conn,"select * from order_produk join menu  
				on order_produk.id_menu=menu.id_menu where no_order='$no_order'");
				while($r=mysqli_fetch_array($tampil))
				{
					$no=$no+1;

					$harga=number_format($r[harga]);
 					$jum_harga=$r[harga]*$r[jumlah];
					$rjum_harga=number_format($jum_harga);
					
					$tot_harga=$tot_harga+$jum_harga;
					$rtot_harga=number_format($tot_harga);


				  echo "<tr class='td' bgcolor='#FFF'>
						<td><span class=title3>$no
						<td><span class=title3>$r[kd_brg]
						<td><span class=title3>$r[nm_menu]
 						<td><center><span class=title3>$r[jumlah]  
						<td dir=rtl><span class=title3>$harga
						<td dir=rtl><span class=title3>$rjum_harga
						 
						";

				}

				echo "<tr><td><td><td><td><td><td dir=rtl><b>$rtot_harga</b>";



				 
				echo "</div></div></table>";


 ?>