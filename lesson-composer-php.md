# Bài Học: Sử Dụng Composer trong PHP và Áp Dụng vào Dự Án Quản Lý Thực Tập

## Giới Thiệu
Composer là một công cụ quản lý thư viện phụ thuộc cho PHP, giúp bạn dễ dàng cài đặt và quản lý các thư viện bên ngoài trong dự án của mình. Composer giúp bạn tiết kiệm thời gian và công sức khi cần tích hợp các thư viện bên ngoài vào dự án.

## Cài Đặt Composer
Để cài đặt Composer, làm theo các bước sau:

1. Download cài đặt Composer từ trang web chính thức: https://getcomposer.org/download/
2. Chạy file cài đặt và làm theo hướng dẫn để hoàn tất cài đặt.

## Sử Dụng Composer
### Tạo File `composer.json`
File `composer.json` chứa thông tin về các thư viện phụ thuộc của dự án. Để tạo file này, sử dụng lệnh sau trong terminal:
```bash
composer init
```
Lệnh này sẽ yêu cầu bạn nhập các thông tin cơ bản về dự án và sau đó tạo ra file `composer.json`.

### Cài Đặt Thư Viện Phụ Thuộc
Để cài đặt một thư viện phụ thuộc, sử dụng lệnh sau:
```bash
composer require <ten-thu-vien>
```
Ví dụ:
```bash
composer require phpoffice/phpspreadsheet
```
Lệnh này sẽ cài đặt thư viện `phpspreadsheet`, một thư viện hữu ích để làm việc với file Excel trong PHP.

### Autoload
Composer cung cấp tính năng autoload để tự động tải các lớp từ thư viện phụ thuộc. Để sử dụng autoload, thêm dòng sau vào đầu file PHP của bạn:
```php
require 'vendor/autoload.php';
```
File `vendor/autoload.php` được Composer tạo ra sau khi cài đặt các thư viện phụ thuộc.

## Áp Dụng Composer vào Dự Án Quản Lý Thực Tập
### Yêu Cầu Kỹ Thuật
- Ngôn ngữ lập trình: PHP.
- Cơ sở dữ liệu: MySQL.
- Hỗ trợ tải lên và xử lý file Excel để nhập danh sách sinh viên.
- Giao diện đơn giản và dễ sử dụng cho cả giảng viên và sinh viên.

# Develop a student internship management website using PHP and MySQL.  

### Features:  
#### **Lecturer Account:**  
- Create internship courses with course ID and name.  
- Import student lists from Excel, including fields: Student ID, last name, first name, phone number, email, major, date of birth, and class ID.  
- Confirm the student list after a successful import.  

#### **Student Account:**  
- Log in using their Student ID as the default username and password.  
- Required to change the password on the first login for security.  

### **Technical Requirements:**  
- Programming Language: PHP.  
- Database: MySQL.  
- Supports Excel file upload and processing for student list imports.  
- Simple and user-friendly interface for both lecturers and students.  

The database configuration is provided in the `/config` directory."



### Tạo Dự Án Quản Lý Thực Tập
1. **Tạo Cơ Sở Dữ Liệu MySQL:**
   ```sql
   CREATE DATABASE IF NOT EXISTS `internship_management` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
   USE `internship_management`;

   CREATE TABLE IF NOT EXISTS `internship_courses` (
     `course_id` int NOT NULL AUTO_INCREMENT,
     `course_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
     `course_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
     `description` text COLLATE utf8mb4_unicode_ci,
     `lecturer_id` int NOT NULL,
     `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
     `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     PRIMARY KEY (`course_id`),
     UNIQUE KEY `course_code` (`course_code`),
     KEY `lecturer_id` (`lecturer_id`),
     CONSTRAINT `internship_courses_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`lecturer_id`)
   ) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

   CREATE TABLE IF NOT EXISTS `lecturers` (
     `lecturer_id` int NOT NULL AUTO_INCREMENT,
     `user_id` int NOT NULL,
     `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
     `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
     `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
     `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
     `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
     `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     PRIMARY KEY (`lecturer_id`),
     KEY `user_id` (`user_id`),
     CONSTRAINT `lecturers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
   ) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

   CREATE TABLE IF NOT EXISTS `students` (
     `student_id` int NOT NULL AUTO_INCREMENT,
     `user_id` int NOT NULL,
     `student_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
     `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
     `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
     `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
     `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
     `major` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
     `dob` date DEFAULT NULL,
     `class_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
     `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
     `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     PRIMARY KEY (`student_id`),
     UNIQUE KEY `student_code` (`student_code`),
     KEY `user_id` (`user_id`),
     CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
   ) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

   CREATE TABLE IF NOT EXISTS `internship_details` (
     `id` int NOT NULL AUTO_INCREMENT,
     `student_id` int NOT NULL,
     `course_id` int NOT NULL,
     `company_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
     `company_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
     `industry` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
     `supervisor_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
     `supervisor_phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
     `supervisor_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
     `start_date` date NOT NULL,
     `end_date` date NOT NULL,
     `job_position` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
     `job_description` text COLLATE utf8mb4_unicode_ci NOT NULL,
     `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
     `feedback` text COLLATE utf8mb4_unicode_ci,
     `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
     `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     PRIMARY KEY (`id`),
     UNIQUE KEY `unique_student_course` (`student_id`,`course_id`),
     KEY `course_id` (`course_id`),
     CONSTRAINT `internship_details_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
     CONSTRAINT `internship_details_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `internship_courses` (`course_id`) ON DELETE CASCADE
   ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

   CREATE TABLE IF NOT EXISTS `student_courses` (
     `id` int NOT NULL AUTO_INCREMENT,
     `student_id` int NOT NULL,
     `course_id` int NOT NULL,
     `status` enum('active','completed','withdrawn') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
     `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
     `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     PRIMARY KEY (`id`),
     UNIQUE KEY `unique_student_course` (`student_id`,`course_id`),
     KEY `course_id` (`course_id`),
     CONSTRAINT `student_courses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
     CONSTRAINT `student_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `internship_courses` (`course_id`)
   ) ENGINE=InnoDB AUTO_INCREMENT=363 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

   CREATE TABLE IF NOT EXISTS `users` (
     `user_id` int NOT NULL AUTO_INCREMENT,
     `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
     `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
     `role` enum('lecturer','student') COLLATE utf8mb4_unicode_ci NOT NULL,
     `is_first_login` tinyint(1) DEFAULT '1',
     `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
     `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     PRIMARY KEY (`user_id`),
     UNIQUE KEY `username` (`username`)
   ) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

2. **Cài Đặt Thư Viện Phụ Thuộc:**
   ```bash
   composer require phpoffice/phpspreadsheet
   composer require phpmailer/phpmailer
   ```

3. **Tạo File `index.php` để Xử Lý Yêu Cầu Đăng Nhập:**
   ```php
   <?php
   session_start();
   require 'vendor/autoload.php';
   $conn = new mysqli('localhost', 'root', '', 'internship_management');

   if ($_SERVER['REQUEST_METHOD'] == 'POST') {
       $username = $_POST['username'];
       $password = $_POST['password'];
       $result = $conn->query("SELECT * FROM students WHERE student_code='$username' AND password='$password'");
       if ($result->num_rows > 0) {
           $_SESSION['user'] = $username;
           header('Location: student_dashboard.php');
       } else {
           echo "Invalid Username or Password";
       }
   }
   ?>
   <form method="post">
       Username: <input type="text" name="username"><br>
       Password: <input type="password" name="password"><br>
       <button type="submit">Login</button>
   </form>
   ```

4. **Xử Lý File Excel Để Nhập Danh Sách Sinh Viên:**
   ```php
   <?php
   require 'vendor/autoload.php';
   use PhpOffice\PhpSpreadsheet\IOFactory;

   if (isset($_POST['upload'])) {
       $file = $_FILES['excel']['tmp_name'];
       $spreadsheet = IOFactory::load($file);
       $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

       $conn = new mysqli('localhost', 'root', '', 'internship_management');
       foreach ($sheetData as $row) {
           $student_code = $row['A'];
           $last_name = $row['B'];
           $first_name = $row['C'];
           $phone = $row['D'];
           $email = $row['E'];
           $major = $row['F'];
           $dob = $row['G'];
           $class_code = $row['H'];
           $conn->query("INSERT INTO students (student_code, last_name, first_name, phone, email, major, dob, class_code) 
                         VALUES ('$student_code', '$last_name', '$first_name', '$phone', '$email', '$major', '$dob', '$class_code')");
       }
       echo "Import thành công!";
   }
   ?>
   <form method="post" enctype="multipart/form-data">
       <input type="file" name="excel">
       <button type="submit" name="upload">Upload</button>
   </form>
   ```

Bài học này cung cấp kiến thức cơ bản về cách sử dụng Composer trong PHP và áp dụng để xây dựng một dự án quản lý thực tập. Bạn có thể mở rộng thêm các tính năng khác và cải tiến giao diện người dùng để đáp ứng tốt hơn nhu cầu của giảng viên và sinh viên.
