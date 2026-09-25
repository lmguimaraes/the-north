<?php
header_remove('X-Powered-By');
http_response_code(404);
exit;
