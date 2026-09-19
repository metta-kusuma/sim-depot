$(document).ready(function() {
    // Inisialisasi Datepicker untuk input tanggal
    $("#tglpesan").datepicker({
        dateFormat: 'yy-mm-dd'
    });

    // // Inisialisasi Timepicker untuk input jam
    // $('#jampesan').timepicker({
    //     timeFormat: 'HH:mm:ss',
    //     interval: 30,
    //     minTime: '00:00:00',
    //     maxTime: '23:59:00',
    //     dynamic: true,
    //     dropdown: true,
    //     scrollbar: true
    // });

    // Dapatkan tanggal dan waktu saat ini sebagai placeholder
    const now = new Date();
    const formattedDate = now.toISOString().split('T')[0];
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const formattedTime = `${hours}:${minutes}:${seconds}`;

    // Set placeholder pada input tanggal dan jam
    $("#tglpesan").attr("placeholder",formattedDate);
    // $("#jampesan").val(formattedTime);
    $("#jampesan").attr("placeholder", formattedTime);
});
