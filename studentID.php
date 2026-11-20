<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kumi University - Student ID Cards</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body{
    background:#e6f2ff;
    padding:20px;
    display:flex;
    flex-direction:column;
    align-items:center;
    min-height:100vh;
}

.id-card-container{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:25px;
    max-width:450px;
    width:100%;
}

.id-card{
    width:8.5cm;
    height:5.4cm;
    background:#fff;
    border-radius:8px;
    overflow:hidden;
    box-shadow:0 5px 15px rgba(0,0,0,.2);
    position:relative;
}

/* BLUE BARS */
.top-blue-bar{background:#1a5f9e;height:15px;}
.middle-blue-bar{background:#1a5f9e;height:5px;}
.footer-bar{
    background:#1a5f9e;
    height:6px;
    position:absolute;
    bottom:0;
    width:100%;
}

/* HEADER */
.header-section{
    height:50px;
    padding:5px 0 5px 55px; /* Reduced right padding, increased left padding */
    display:flex;
    align-items:center;
    position:relative;
}

.university-logo{
    position:absolute;
    top:1.5px;
    left:7px;
}

.logo-image{
    height:45px;
    object-fit:contain;
}

.university-header{
    width:100%;
    text-align:center; /* Center both lines */
    padding-right: 10px; /* Added right padding */
}

.university-name{
    font-size:25px;
    font-weight:700;
    color:#1a5f9e;
    line-height: 1; /* Reduced line spacing */
    margin-bottom: 2px; /* Space between lines */
    font-family: 'Times New Roman', Times, serif;
    text-align: center; /* Center the text */
    width: 100%;
}

.card-title{
    font-size:15px;
    font-weight:700;
    color:red;
    text-transform:uppercase;
    line-height: 1; /* Reduced line spacing */
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    font-family: 'Times New Roman', Times, serif;
    text-align: center; /* Center the text */
    width: 100%;
    margin: 0 auto; /* Center the element */
    position: relative;
}

/* Center STUDENT'S IDENTITY CARD under KUMI UNIVERSITY */
.card-title-container {
    display: flex;
    justify-content: center;
    width: 100%;
}

/* CONTENT */
.card-content{
    display:flex;
    height:calc(100% - 90px);
    padding:3px 0 0 2px;
    position:relative;
}

.photo-section{width:68px; position:relative; z-index:3;}

/* ===== WATERMARK BEHIND PHOTO ===== */
.photo-section::before{
    content:"";
    position:absolute;
    top:-1px; /* Start from just below middle blue bar */
    left:0;
    width:100%;
    height:calc(100% + 10px); /* Extend to bottom blue bar */
    background-image:url("KU Logo.png");
    background-repeat:repeat;
    background-size:33.33% 16.66%; /* 3 columns × 6 rows pattern */
    background-position:0 0;
    opacity:0.25;
    z-index:1; /* Behind photo */
    pointer-events:none;
}


.photo-container{
    width:67px;
    height:95px;
    overflow:hidden;
    position:relative;
    z-index:3; /* Photo above watermark */
}

/* DETAILS */
.details-section{
    flex:1;
    display:flex;
    position:relative;
    overflow:visible; /* Changed from hidden to visible */
    padding-bottom: 8px; /* Added padding for signature */
}

/* ===== RIGHT WATERMARK - REDUCED WIDTH, 4 COLUMNS ===== */
.details-section::before{
    content:"";
    position:absolute;
    top:-1px; /* Start from just below middle blue bar */
    right:0;
    width:calc(40% - 0.3px); /* Reduced width from 50% to 40% */
    height:calc(100% + 15px); /* Increased to extend further down */
    background-image:url("KU Logo.png");
    background-repeat:repeat;
    background-size:25% 16.66%; /* 4 columns × 6 rows pattern */
    background-position:100% 0;
    opacity:0.25;
    z-index:0;
    pointer-events:none;
    bottom: 0; /* Ensure it reaches the bottom */
}
/* COLUMNS */
.column{
    display:flex;
    flex-direction:column;
}

.column-left{flex:1;}
.column-right{
    flex:1.3;
    top:-4px;
    position: relative;
    z-index: 3; /* Increased z-index to be above watermark */
}

/* Ensure content stays above watermarks */
.column-left,
.column-right{
    position:relative;
    z-index:3; /* Increased from 2 to 3 */
}

/* ROWS */
.column-left .row{
    display:flex;
    flex-direction:column;
    margin-bottom:4px;
    height:20px;
}

.column-right .row{
    display:flex;
    flex-direction:column;
    margin-bottom:2px; /* Reduced from 4px to 2px for tighter spacing */
    height:18px;
}

/* HORIZONTAL ROWS */
.column-right .row:first-child,
.column-right .row:nth-child(5){
    flex-direction:row;
    align-items:center;
}

/* TEXT */
.field-label{
    font-size:9px;
    font-weight:bold;
    color:#333;
}

.field-value{
    font-size:9px;
    font-weight:bold;
    color:#1a5f9e;
}

.card-number{color:red;font-weight:bold;font-size:8.5px;}
.reg-no-box{color:#1a5f9e;font-weight:bold;font-size: 9px;}

.signature-area{
    font-size:7px;
    font-weight:bold;
    margin-top:0px; /* Reduced from 2px to 0px to bring it closer */
    position: relative;
    z-index: 4; /* Ensure signature text is above everything */
    background-color: rgba(255, 255, 255, 0.7); /* Light background to ensure readability */
    padding: 1px 2px;
    border-radius: 2px;
}

/* PRINT OPTIMIZATION */
@media print{
    body {
        padding: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        background: none;
    }

    .id-card {
        box-shadow: none;
        border: 1px solid #ccc;
        page-break-inside: avoid;
        margin: 0;
    }
    
    /* Ensure watermarks print */
    .photo-section::before,
    .details-section::before{
        opacity:0.25 !important;
        -webkit-print-color-adjust:exact;
        print-color-adjust:exact;
    }
    
    .signature-area {
        background-color: rgba(255, 255, 255, 0.9); /* More opaque for print */
    }
}

/* Remove background shadow for print preview */
@media print and (color) {
    .id-card{
        box-shadow:none !important;
    }
}
</style>
</head>

<body>

<div class="id-card-container">

<!-- ================= FIRST CARD ================= -->
<div class="id-card">
<div class="top-blue-bar"></div>

<div class="header-section">
    <div class="university-logo">
        <img src="KU Logo.png" class="logo-image">
    </div>
    <div class="university-header">
        <div class="university-name">KUMI UNIVERSITY</div>
        <div class="card-title" style="display: block; text-align: center; width: 100%;">STUDENT'S IDENTITY CARD</div>
    </div>
</div>

<div class="middle-blue-bar"></div>

<div class="card-content">
<div class="photo-section">
    <div class="photo-container">
        <img src="LOBOLIA DAVID.png" style="width:100%;height:100%;object-fit:cover;">
    </div>
</div>

<div class="details-section">
<div class="column column-left">
    <div class="row"><div class="field-label">Surname:</div><div class="field-value">LOBOLIA</div></div>
    <div class="row"><div class="field-label">Given Name:</div><div class="field-value">WALTER MARK OKELLO</div></div>
    <div class="row"><div class="field-label">Nationality:</div><div class="field-value">UGA</div></div>
    <div class="row"><div class="field-label">Date of Issue:</div><div class="field-value">26.03.2022</div></div>
    <div class="signature-area">HOLDERS SIGNATURE:</div>
</div>

<div class="column column-right">
    <div class="row" style="margin-bottom: 2px;"><div class="field-label">Card No.:</div><div class="card-number">EDU-STU-2025-02680</div></div>
    <div class="row" style="margin-bottom: 2px;"><div class="field-label">Reg No.:</div><div class="reg-no-box">22.U.002.CIT-T</div></div>
    <div class="row" style="margin-bottom: 2px;"><div class="field-label">Faculty:</div><div class="field-value">SCIE & TECH</div></div>
    <div class="row" style="margin-bottom: 2px;"><div class="field-label">Course:</div><div class="field-value">CIT</div></div>
    <div class="row" style="margin-bottom: 2px;"><div class="field-label">Expiry Date:</div><div class="field-value">31.12.2024</div></div>
    <div class="signature-area">ACADEMIC REGISTRAR'S SIGNATURE:</div>
</div>
</div>
</div>

<div class="footer-bar"></div>
</div>

<!-- ================= SECOND CARD ================= -->
<div class="id-card">
<div class="top-blue-bar"></div>

<div class="header-section">
    <div class="university-logo">
        <img src="KU Logo.png" class="logo-image">
    </div>
    <div class="university-header">
        <div class="university-name">KUMI UNIVERSITY</div>
        <div class="card-title" style="display: block; text-align: center; width: 100%;">STUDENT'S IDENTITY CARD</div>
    </div>
</div>

<div class="middle-blue-bar"></div>

<div class="card-content">
<div class="photo-section">
    <div class="photo-container">
        <img src="ELIGU THOMAS.png" style="width:100%;height:100%;object-fit:cover;">
    </div>
</div>

<div class="details-section">
<div class="column column-left">
    <div class="row"><div class="field-label">Surname:</div><div class="field-value">ELIGU</div></div>
    <div class="row"><div class="field-label">Given Name:</div><div class="field-value">THOMAS</div></div>
    <div class="row"><div class="field-label">Nationality:</div><div class="field-value">UGA</div></div>
    <div class="row"><div class="field-label">Date of Issue:</div><div class="field-value">26.08.2022</div></div>
    <div class="signature-area">HOLDERS SIGNATURE:</div>
</div>

<div class="column column-right">
    <div class="row" style="margin-bottom: 2px;"><div class="field-label">Card No.:</div><div class="card-number">ST002640</div></div>
    <div class="row" style="margin-bottom: 2px;"><div class="field-label">Reg No.:</div><div class="reg-no-box">22.U.007.BAED-T</div></div>
    <div class="row" style="margin-bottom: 2px;"><div class="field-label">Faculty:</div><div class="field-value">EDUC & LANG</div></div>
    <div class="row" style="margin-bottom: 2px;"><div class="field-label">Course:</div><div class="field-value">BAED</div></div>
    <div class="row" style="margin-bottom: 2px;"><div class="field-label">Expiry Date:</div><div class="field-value">31.12.2025</div></div>
    <div class="signature-area">ACADEMIC REGISTRAR'S SIGNATURE:</div>
</div>
</div>
</div>

<div class="footer-bar"></div>
</div>

</div>
</body>
</html>