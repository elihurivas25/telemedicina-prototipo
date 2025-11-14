-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: localhost    Database: telemedicina
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `adjunto`
--

DROP TABLE IF EXISTS `adjunto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adjunto` (
  `idAdjunto` varchar(36) NOT NULL,
  `idHistoriaClinica` varchar(36) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `url` varchar(512) DEFAULT NULL,
  `tamanoMb` decimal(6,2) DEFAULT NULL,
  PRIMARY KEY (`idAdjunto`),
  KEY `idx_Adjunto_Historia` (`idHistoriaClinica`),
  CONSTRAINT `fk_Adjunto_Historia` FOREIGN KEY (`idHistoriaClinica`) REFERENCES `historiaclinica` (`idHistoriaClinica`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chatmensaje`
--

DROP TABLE IF EXISTS `chatmensaje`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chatmensaje` (
  `idChatMensaje` varchar(36) NOT NULL,
  `idCita` varchar(36) NOT NULL,
  `autorRol` enum('PACIENTE','MEDICO') NOT NULL,
  `contenido` text,
  `timestamp` datetime DEFAULT NULL,
  PRIMARY KEY (`idChatMensaje`),
  KEY `idx_ChatMensaje_CitaTs` (`idCita`,`timestamp`),
  CONSTRAINT `fk_ChatMensaje_Cita` FOREIGN KEY (`idCita`) REFERENCES `cita` (`idCita`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cita`
--

DROP TABLE IF EXISTS `cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cita` (
  `idCita` varchar(36) NOT NULL,
  `idPaciente` varchar(36) NOT NULL,
  `idMedico` varchar(36) NOT NULL,
  `especialidad` enum('General','Pediatría','Psicología','Familiar','Geriatría','Cardiología') DEFAULT NULL,
  `inicio` datetime DEFAULT NULL,
  `fin` datetime DEFAULT NULL,
  `estado` enum('Reservada','PendientePago','Confirmada','EnCurso','Completada','Cancelada') NOT NULL,
  `canal` enum('Chat','Video') DEFAULT NULL,
  PRIMARY KEY (`idCita`),
  KEY `idx_Cita_MedicoInicio` (`idMedico`,`inicio`),
  KEY `idx_Cita_PacienteInicio` (`idPaciente`,`inicio`),
  KEY `idx_Cita_Estado` (`estado`),
  CONSTRAINT `fk_Cita_Medico` FOREIGN KEY (`idMedico`) REFERENCES `medico` (`idMedico`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_Cita_Paciente` FOREIGN KEY (`idPaciente`) REFERENCES `paciente` (`idPaciente`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consentimiento`
--

DROP TABLE IF EXISTS `consentimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consentimiento` (
  `idConsentimiento` varchar(36) NOT NULL,
  `idPaciente` varchar(36) DEFAULT NULL,
  `versionTexto` varchar(50) DEFAULT NULL,
  `fechaAceptacion` datetime DEFAULT NULL,
  `ipAceptacion` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`idConsentimiento`),
  KEY `idx_Consent_PacienteFecha` (`idPaciente`,`fechaAceptacion`),
  CONSTRAINT `fk_Consentimiento_Paciente` FOREIGN KEY (`idPaciente`) REFERENCES `paciente` (`idPaciente`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `disponibilidad`
--

DROP TABLE IF EXISTS `disponibilidad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disponibilidad` (
  `idDisponibilidad` varchar(36) NOT NULL,
  `idMedico` varchar(36) NOT NULL,
  `diaSemana` tinyint DEFAULT NULL,
  `horaInicio` time DEFAULT NULL,
  `horaFin` time DEFAULT NULL,
  `duracionBloqueMin` int DEFAULT NULL,
  PRIMARY KEY (`idDisponibilidad`),
  KEY `idx_Disp_MedicoDia` (`idMedico`,`diaSemana`),
  CONSTRAINT `fk_Disponibilidad_Medico` FOREIGN KEY (`idMedico`) REFERENCES `medico` (`idMedico`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `historiaclinica`
--

DROP TABLE IF EXISTS `historiaclinica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historiaclinica` (
  `idHistoriaClinica` varchar(36) NOT NULL,
  `idPaciente` varchar(36) NOT NULL,
  `alergias` text,
  `antecedentes` text,
  `medicamentos` text,
  `notaEvolucion` text,
  `ultimaActualizacion` datetime DEFAULT NULL,
  PRIMARY KEY (`idHistoriaClinica`),
  UNIQUE KEY `idPaciente` (`idPaciente`),
  CONSTRAINT `fk_Historia_Paciente` FOREIGN KEY (`idPaciente`) REFERENCES `paciente` (`idPaciente`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logauditoria`
--

DROP TABLE IF EXISTS `logauditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logauditoria` (
  `idLogAuditoria` varchar(36) NOT NULL,
  `idUsuario` varchar(36) DEFAULT NULL,
  `accion` varchar(100) DEFAULT NULL,
  `detalle` json DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`idLogAuditoria`),
  KEY `idx_LogAuditoria_UserTs` (`idUsuario`,`timestamp`),
  CONSTRAINT `fk_LogAuditoria_Usuario` FOREIGN KEY (`idUsuario`) REFERENCES `usuario` (`idUsuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `medico`
--

DROP TABLE IF EXISTS `medico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medico` (
  `idMedico` varchar(36) NOT NULL,
  `idUsuario` varchar(36) NOT NULL,
  `cedulaProfesional` varchar(50) DEFAULT NULL,
  `especialidad` enum('General','Pediatría','Psicología','Familiar','Geriatría','Cardiología') NOT NULL,
  `bio` text,
  PRIMARY KEY (`idMedico`),
  UNIQUE KEY `idUsuario` (`idUsuario`),
  CONSTRAINT `fk_Medico_Usuario` FOREIGN KEY (`idUsuario`) REFERENCES `usuario` (`idUsuario`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `paciente`
--

DROP TABLE IF EXISTS `paciente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paciente` (
  `idPaciente` varchar(36) NOT NULL,
  `idUsuario` varchar(36) NOT NULL,
  `fechaNacimiento` date DEFAULT NULL,
  `sexo` enum('F','M','Otro') DEFAULT NULL,
  `contactoEmergencia` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`idPaciente`),
  UNIQUE KEY `idUsuario` (`idUsuario`),
  CONSTRAINT `fk_Paciente_Usuario` FOREIGN KEY (`idUsuario`) REFERENCES `usuario` (`idUsuario`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pago`
--

DROP TABLE IF EXISTS `pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pago` (
  `idPago` varchar(36) NOT NULL,
  `idCita` varchar(36) NOT NULL,
  `monto` decimal(10,2) DEFAULT NULL,
  `moneda` varchar(10) DEFAULT NULL,
  `proveedor` enum('Stripe','MercadoPago','PayPal') DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `estado` enum('Autorizado','Rechazado','Reembolsado') NOT NULL,
  `createdAt` datetime DEFAULT NULL,
  PRIMARY KEY (`idPago`),
  UNIQUE KEY `idCita` (`idCita`),
  CONSTRAINT `fk_Pago_Cita` FOREIGN KEY (`idCita`) REFERENCES `cita` (`idCita`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `idUsuario` varchar(36) NOT NULL,
  `email` varchar(255) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `rol` enum('PACIENTE','MEDICO','ADMIN') NOT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `telefono` varchar(32) DEFAULT NULL,
  `zonaHoraria` varchar(64) DEFAULT NULL,
  `createdAt` datetime DEFAULT NULL,
  `updatedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`idUsuario`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `videosesion`
--

DROP TABLE IF EXISTS `videosesion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `videosesion` (
  `idVideoSesion` varchar(36) NOT NULL,
  `idCita` varchar(36) NOT NULL,
  `proveedor` varchar(50) DEFAULT NULL,
  `salaId` varchar(100) DEFAULT NULL,
  `inicio` datetime DEFAULT NULL,
  `fin` datetime DEFAULT NULL,
  `metricaConexion` json DEFAULT NULL,
  PRIMARY KEY (`idVideoSesion`),
  KEY `idx_VideoSesion_Cita` (`idCita`),
  CONSTRAINT `fk_VideoSesion_Cita` FOREIGN KEY (`idCita`) REFERENCES `cita` (`idCita`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-13 15:56:40
