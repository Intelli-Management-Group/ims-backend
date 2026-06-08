<?php

require __DIR__.'/../vendor/autoload.php';

// Force test environment variables before Laravel bootstraps.
// These override shell exports so the immutable Dotenv repository
// sees 'sqlite' and never writes the shell's MySQL values.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('JWT_SECRET=YzvcZsadYTCpMIqoOzYH9146lMXMv6fhxj1t0SdjuXIuTje0igY0uU900xBIzIT3');
