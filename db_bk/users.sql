/*
SQLyog Ultimate v11.11 (32 bit)
MySQL - 8.0.30 : Database - gps_app
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
USE `gps_app`;

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_code` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `preferences` json DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`),
  KEY `users_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `users` */

insert  into `users`(`id`,`name`,`email`,`country_code`,`phone`,`email_verified_at`,`password`,`remember_token`,`created_at`,`updated_at`,`role`,`status`,`preferences`,`avatar`) values (1,'Admin','admin@demo.test','','','2025-12-09 12:44:09','$2y$12$4E/mHOwInDjLM/a8DTdyw.EXM63l3JwVYRBKWevlOOy.epTfweyR2','j7GDw1u7cQfSk4VbUiZJedzEuUYsuCXKC0iqfBiwa7TnnGRxPEhL9CUZw1sc','2025-12-09 12:44:09','2026-05-17 08:26:06','admin','active','{\"locale\": \"ar\", \"map_tour\": \"dismiss\"}',NULL),(2,'Zahid Hussain','zhg786@gmail.commm','','','2025-12-09 12:44:09','$2y$12$4E/mHOwInDjLM/a8DTdyw.EXM63l3JwVYRBKWevlOOy.epTfweyR2','GRXPjOVnWdyBC7zxt8rOk1ERTDfwm37G7RwIU4Nfdi1byYNa1ZXezEwPHtqS','2025-12-09 12:44:09','2026-05-11 06:26:40','user','active',NULL,NULL),(3,'Umar','umar@user.com','','',NULL,'$2y$12$4E/mHOwInDjLM/a8DTdyw.EXM63l3JwVYRBKWevlOOy.epTfweyR2',NULL,'2025-12-09 17:41:02','2026-05-11 06:26:40','user','active',NULL,NULL),(4,'ali','ali@gmail.com','','',NULL,'$2y$12$4E/mHOwInDjLM/a8DTdyw.EXM63l3JwVYRBKWevlOOy.epTfweyR2',NULL,'2025-12-13 16:08:34','2026-05-11 06:26:40','user','active',NULL,NULL),(5,'awal','awal@gmail.com','+92','9203003026824',NULL,'$2y$12$4E/mHOwInDjLM/a8DTdyw.EXM63l3JwVYRBKWevlOOy.epTfweyR2',NULL,'2025-12-14 07:59:01','2026-05-11 06:26:40','user','active',NULL,NULL),(6,'Hasnain Ali','hasnain@site.com','+92','9203312343433',NULL,'$2y$12$4E/mHOwInDjLM/a8DTdyw.EXM63l3JwVYRBKWevlOOy.epTfweyR2',NULL,'2025-12-15 13:06:59','2026-05-11 06:26:40','user','active',NULL,NULL),(31,'Bilal','bilal@gmail.com','+966','966545571620',NULL,'$2y$12$4E/mHOwInDjLM/a8DTdyw.EXM63l3JwVYRBKWevlOOy.epTfweyR2',NULL,'2025-12-22 13:14:57','2026-05-11 06:26:40','user','active',NULL,NULL),(32,'Zahid Hussain','zhg786@gmail.com','+92','923312343433','2025-12-22 13:17:16','$2y$12$4E/mHOwInDjLM/a8DTdyw.EXM63l3JwVYRBKWevlOOy.epTfweyR2','jo6mei2ysaj3cFaNbB7uRhVyNdiPoHrm5SKE38XjrUE3dv6hLpNUsCNbb2Hi','2025-12-22 13:16:34','2026-05-17 10:40:15','user','active','{\"locale\": \"en\", \"map_tour\": \"dismiss\"}',NULL);

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
