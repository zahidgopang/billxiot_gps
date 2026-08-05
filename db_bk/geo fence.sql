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

/*Table structure for table `geofences` */

DROP TABLE IF EXISTS `geofences`;

CREATE TABLE `geofences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `device_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('polygon','circle') COLLATE utf8mb4_unicode_ci NOT NULL,
  `coords` longtext COLLATE utf8mb4_unicode_ci,
  `center` longtext COLLATE utf8mb4_unicode_ci,
  `radius` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `geofences` */

insert  into `geofences`(`id`,`device_id`,`name`,`type`,`coords`,`center`,`radius`,`created_at`,`updated_at`) values (10,12,'circle','circle',NULL,'[25.392124523302385,68.9665904134997]',3870,'2026-05-17 02:36:40','2026-05-17 02:36:40'),(11,12,'New Geofence','circle',NULL,'[25.38331132479019,68.98535353205652]',6027,'2026-05-17 04:28:27','2026-05-17 04:28:27'),(12,12,'New Geofence','polygon','[[24.856512759219044,46.63891405742337],[24.772374195061367,46.53797716777493],[24.8197481187167,46.67942614238431]]',NULL,NULL,'2026-05-17 08:13:44','2026-05-17 08:13:44');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
