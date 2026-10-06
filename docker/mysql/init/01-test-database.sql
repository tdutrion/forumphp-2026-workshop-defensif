-- Database used by PHPUnit (Doctrine adds the "_test" suffix in the test environment).
CREATE DATABASE IF NOT EXISTS app_test;
GRANT ALL PRIVILEGES ON app_test.* TO 'app'@'%';
