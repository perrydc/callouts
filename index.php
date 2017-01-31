<?php
require('controls.php');
?>
<html>
<body>
<h1>"Download NPR One at:<BR>
<? echo $nprLink; ?><BR>
(all lower case)"</h1>
<BR><BR>
<h3>or, if you'd like to use your own domain, simply place <a href="<? echo $fileLink; ?>">this file</a> in a directory called "alwayson" in your server's root folder and say:</h3>
<BR><BR>
<h1>"Download NPR One at:<BR>
<? echo $stationLink; ?><BR>
(all lower case)"</h1>
<BR><BR>
</form>
</body>
</html>