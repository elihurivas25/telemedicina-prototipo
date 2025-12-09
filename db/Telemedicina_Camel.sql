-- Script de creación de la base de datos del prototipo de Telemedicina
-- Autor: Elihú
-- Proyecto: Prototipo de Telemedicina
-- Descripción: Definición de tablas y relaciones del sistema
-- Motor: MySQL
-- Convención: Tablas en CamelCase y columnas en camelCase

SET sql_mode = 'STRICT_ALL_TABLES';

CREATE TABLE Usuario (
  idUsuario    VARCHAR(36) PRIMARY KEY,
  email        VARCHAR(255) NOT NULL UNIQUE,
  passwordHash VARCHAR(255) NOT NULL,
  rol          ENUM('PACIENTE','MEDICO','ADMIN') NOT NULL,
  nombre       VARCHAR(150),
  telefono     VARCHAR(32),
  zonaHoraria  VARCHAR(64),
  createdAt    DATETIME,
  updatedAt    DATETIME
) ENGINE=InnoDB;

CREATE TABLE Medico (
  idMedico            VARCHAR(36) PRIMARY KEY,
  idUsuario           VARCHAR(36) NOT NULL UNIQUE,
  cedulaProfesional   VARCHAR(50),
  especialidad        ENUM('General','Pediatría','Psicología','Familiar','Geriatría','Cardiología') NOT NULL,
  bio                 TEXT,
  CONSTRAINT fk_Medico_Usuario FOREIGN KEY (idUsuario) REFERENCES Usuario(idUsuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Paciente (
  idPaciente          VARCHAR(36) PRIMARY KEY,
  idUsuario           VARCHAR(36) NOT NULL UNIQUE,
  fechaNacimiento     DATE,
  sexo                ENUM('F','M','Otro'),
  contactoEmergencia  VARCHAR(150),
  CONSTRAINT fk_Paciente_Usuario FOREIGN KEY (idUsuario) REFERENCES Usuario(idUsuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Disponibilidad (
  idDisponibilidad   VARCHAR(36) PRIMARY KEY,
  idMedico           VARCHAR(36) NOT NULL,
  diaSemana          TINYINT,
  horaInicio         TIME,
  horaFin            TIME,
  duracionBloqueMin  INT,
  INDEX idx_Disp_MedicoDia (idMedico, diaSemana),
  CONSTRAINT fk_Disponibilidad_Medico FOREIGN KEY (idMedico) REFERENCES Medico(idMedico) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Cita (
  idCita        VARCHAR(36) PRIMARY KEY,
  idPaciente    VARCHAR(36) NOT NULL,
  idMedico      VARCHAR(36) NOT NULL,
  especialidad  ENUM('General','Pediatría','Psicología','Familiar','Geriatría','Cardiología'),
  inicio        DATETIME,
  fin           DATETIME,
  estado        ENUM('Reservada','PendientePago','Confirmada','EnCurso','Completada','Cancelada') NOT NULL,
  canal         ENUM('Chat','Video'),
  INDEX idx_Cita_MedicoInicio (idMedico, inicio),
  INDEX idx_Cita_PacienteInicio (idPaciente, inicio),
  INDEX idx_Cita_Estado (estado),
  CONSTRAINT fk_Cita_Paciente FOREIGN KEY (idPaciente) REFERENCES Paciente(idPaciente) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_Cita_Medico   FOREIGN KEY (idMedico)   REFERENCES Medico(idMedico)   ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Pago (
  idPago      VARCHAR(36) PRIMARY KEY,
  idCita      VARCHAR(36) NOT NULL UNIQUE,
  monto       DECIMAL(10,2),
  moneda      VARCHAR(10),
  proveedor   ENUM('Stripe','MercadoPago','PayPal'),
  referencia  VARCHAR(100),
  estado      ENUM('Autorizado','Rechazado','Reembolsado') NOT NULL,
  createdAt   DATETIME,
  CONSTRAINT fk_Pago_Cita FOREIGN KEY (idCita) REFERENCES Cita(idCita) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Consentimiento (
  idConsentimiento  VARCHAR(36) PRIMARY KEY,
  idPaciente        VARCHAR(36),
  versionTexto      VARCHAR(50),
  fechaAceptacion   DATETIME,
  ipAceptacion      VARCHAR(64),
  INDEX idx_Consent_PacienteFecha (idPaciente, fechaAceptacion),
  CONSTRAINT fk_Consentimiento_Paciente FOREIGN KEY (idPaciente) REFERENCES Paciente(idPaciente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE HistoriaClinica (
  idHistoriaClinica   VARCHAR(36) PRIMARY KEY,
  idPaciente          VARCHAR(36) NOT NULL UNIQUE,
  alergias            TEXT,
  antecedentes        TEXT,
  medicamentos        TEXT,
  notaEvolucion       TEXT,
  ultimaActualizacion DATETIME,
  CONSTRAINT fk_Historia_Paciente FOREIGN KEY (idPaciente) REFERENCES Paciente(idPaciente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Adjunto (
  idAdjunto         VARCHAR(36) PRIMARY KEY,
  idHistoriaClinica VARCHAR(36) NOT NULL,
  tipo              VARCHAR(50),
  url               VARCHAR(512),
  tamanoMb          DECIMAL(6,2),
  INDEX idx_Adjunto_Historia (idHistoriaClinica),
  CONSTRAINT fk_Adjunto_Historia FOREIGN KEY (idHistoriaClinica) REFERENCES HistoriaClinica(idHistoriaClinica) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ChatMensaje (
  idChatMensaje VARCHAR(36) PRIMARY KEY,
  idCita        VARCHAR(36) NOT NULL,
  autorRol      ENUM('PACIENTE','MEDICO') NOT NULL,
  contenido     TEXT,
  timestamp     DATETIME,
  INDEX idx_ChatMensaje_CitaTs (idCita, timestamp),
  CONSTRAINT fk_ChatMensaje_Cita FOREIGN KEY (idCita) REFERENCES Cita(idCita) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE VideoSesion (
  idVideoSesion    VARCHAR(36) PRIMARY KEY,
  idCita           VARCHAR(36) NOT NULL,
  proveedor        VARCHAR(50),
  salaId           VARCHAR(100),
  inicio           DATETIME,
  fin              DATETIME,
  metricaConexion  JSON,
  INDEX idx_VideoSesion_Cita (idCita),
  CONSTRAINT fk_VideoSesion_Cita FOREIGN KEY (idCita) REFERENCES Cita(idCita) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE LogAuditoria (
  idLogAuditoria VARCHAR(36) PRIMARY KEY,
  idUsuario      VARCHAR(36), -- permite NULL para ON DELETE SET NULL
  accion         VARCHAR(100),
  detalle        JSON,
  timestamp      DATETIME,
  ip             VARCHAR(64),
  INDEX idx_LogAuditoria_UserTs (idUsuario, timestamp),
  CONSTRAINT fk_LogAuditoria_Usuario
      FOREIGN KEY (idUsuario)
      REFERENCES Usuario(idUsuario)
      ON UPDATE CASCADE
      ON DELETE SET NULL
) ENGINE=InnoDB;
