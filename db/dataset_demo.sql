-- Dataset de demostración para el prototipo de Telemedicina
-- Caso de titulación 2025 - Instituto Tecnológico de Durango
-- Incluye datos de ejemplo para usuarios, médicos, pacientes, citas y módulos relacionados.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Usuarios de ejemplo
INSERT INTO `usuario` (`idUsuario`, `email`, `passwordHash`, `rol`, `nombre`, `telefono`, `zonaHoraria`, `createdAt`, `updatedAt`) VALUES
('u1', 'paciente1@example.com', 'demoHash1', 'PACIENTE', 'Ana Pérez', '+52-618-111-1111', 'America/Mexico_City', '2025-11-01 09:00:00', '2025-11-01 09:00:00'),
('u2', 'paciente2@example.com', 'demoHash2', 'PACIENTE', 'Luis Gómez', '+52-618-222-2222', 'America/Mexico_City', '2025-11-01 09:05:00', '2025-11-01 09:05:00'),
('u3', 'medico1@example.com', 'demoHash3', 'MEDICO', 'Dra. María Ruiz', '+52-618-333-3333', 'America/Mexico_City', '2025-11-01 09:10:00', '2025-11-01 09:10:00'),
('u4', 'medico2@example.com', 'demoHash4', 'MEDICO', 'Dr. Carlos Silva', '+52-618-444-4444', 'America/Mexico_City', '2025-11-01 09:15:00', '2025-11-01 09:15:00'),
('u5', 'admin@example.com', 'demoHash5', 'ADMIN', 'Admin Demo', '+52-618-555-5555', 'America/Mexico_City', '2025-11-01 09:20:00', '2025-11-01 09:20:00');

-- Médicos de ejemplo
INSERT INTO `medico` (`idMedico`, `idUsuario`, `cedulaProfesional`, `especialidad`, `bio`) VALUES
('m1', 'u3', '1234567', 'General', 'Médico general, atención primaria y medicina preventiva.'),
('m2', 'u4', '7654321', 'Psicología', 'Psicoterapia breve, ansiedad y manejo del estrés.');

-- Pacientes de ejemplo
INSERT INTO `paciente` (`idPaciente`, `idUsuario`, `fechaNacimiento`, `sexo`, `contactoEmergencia`) VALUES
('p1', 'u1', '1995-04-12', 'F', 'Juan Pérez, +52-618-000-0000'),
('p2', 'u2', '1992-09-25', 'M', 'María López, +52-618-999-9999');

-- Historia clínica
INSERT INTO `historiaclinica` (`idHistoriaClinica`, `idPaciente`, `alergias`, `antecedentes`, `medicamentos`, `notaEvolucion`, `ultimaActualizacion`) VALUES
('h1', 'p1', 'Alergia a penicilina', 'Asma infantil controlada', 'Salbutamol inhalado según indicación', 'Sin crisis recientes, buen control.', '2025-11-18 09:00:00'),
('h2', 'p2', 'Sin alergias conocidas', 'Migraña recurrente', 'Paracetamol 500 mg PRN', 'Reporta disminución en frecuencia de cefaleas.', '2025-11-19 18:30:00');

-- Consentimiento informado
INSERT INTO `consentimiento` (`idConsentimiento`, `idPaciente`, `versionTexto`, `fechaAceptacion`, `ipAceptacion`) VALUES
('cons1', 'p1', 'v1.0', '2025-11-20 09:00:00', '127.0.0.1'),
('cons2', 'p2', 'v1.0', '2025-11-21 10:30:00', '127.0.0.1');

-- Disponibilidad de médicos
INSERT INTO `disponibilidad` (`idDisponibilidad`, `idMedico`, `diaSemana`, `horaInicio`, `horaFin`, `duracionBloqueMin`) VALUES
('d1', 'm1', 1, '09:00:00', '12:00:00', 30),
('d2', 'm2', 3, '15:00:00', '18:00:00', 45);

-- Citas de ejemplo
INSERT INTO `cita` (`idCita`, `idPaciente`, `idMedico`, `especialidad`, `inicio`, `fin`, `estado`, `canal`) VALUES
('c1', 'p1', 'm1', 'General', '2025-11-26 10:00:00', '2025-11-26 10:30:00', 'Confirmada', 'Video'),
('c2', 'p2', 'm2', 'Psicología', '2025-11-27 15:00:00', '2025-11-27 15:45:00', 'Reservada', 'Chat');

-- Pagos simulados
INSERT INTO `pago` (`idPago`, `idCita`, `monto`, `moneda`, `proveedor`, `referencia`, `estado`, `createdAt`) VALUES
('pay1', 'c1', 600.00, 'MXN', 'Stripe', 'TEST-C1-600MXN', 'Autorizado', '2025-11-25 12:00:00');

-- Sesión de videollamada
INSERT INTO `videosesion` (`idVideoSesion`, `idCita`, `proveedor`, `salaId`, `inicio`, `fin`, `metricaConexion`) VALUES
('v1', 'c1', 'Jitsi', 'sala-demo-c1', '2025-11-26 10:00:10', '2025-11-26 10:29:50', '{\"bitrateKbps\": 1500, \"congelamientos\": 1}');

-- Adjuntos de historia clínica
INSERT INTO `adjunto` (`idAdjunto`, `idHistoriaClinica`, `tipo`, `url`, `tamanoMb`) VALUES
('a1', 'h1', 'Laboratorio', 'https://telemedproyecto.com/evidencias/labs_ana_2025.pdf', 0.80),
('a2', 'h2', 'Imagen', 'https://telemedproyecto.com/evidencias/rx_luis_2025.jpg', 1.20);

-- Mensajes de chat
INSERT INTO `chatmensaje` (`idChatMensaje`, `idCita`, `autorRol`, `contenido`, `timestamp`) VALUES
('msg1', 'c1', 'PACIENTE', 'Buenos días doctora, tengo tos desde hace 3 días.', '2025-11-26 10:02:00'),
('msg2', 'c1', 'MEDICO', 'Buenos días Ana, ¿tienes fiebre o dificultad para respirar?', '2025-11-26 10:03:10'),
('msg3', 'c2', 'PACIENTE', 'Me he sentido muy ansioso estas semanas.', '2025-11-27 15:02:00'),
('msg4', 'c2', 'MEDICO', 'Gracias por compartirlo, vamos a revisar tus síntomas con calma.', '2025-11-27 15:03:30');

-- Log de auditoría
INSERT INTO `logauditoria` (`idLogAuditoria`, `idUsuario`, `accion`, `detalle`, `timestamp`, `ip`) VALUES
('log1', 'u1', 'LOGIN', '{\"resultado\": \"OK\"}', '2025-11-25 08:00:00', '201.0.0.1'),
('log2', 'u3', 'CREAR_CITA', '{\"idCita\": \"c1\", \"idPaciente\": \"p1\"}', '2025-11-25 09:00:00', '201.0.0.2'),
('log3', 'u2', 'ACEPTAR_CONSENTIMIENTO', '{\"idConsentimiento\": \"cons2\"}', '2025-11-25 10:00:00', '201.0.0.3');

COMMIT;