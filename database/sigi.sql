-- SIGI – Sistema Integral de Gestión Inmobiliaria
-- Importar desde phpMyAdmin (pestaña "Importar") o: mysql -u root < sigi.sql
CREATE DATABASE IF NOT EXISTS sigi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sigi;

DROP TABLE IF EXISTS auditoria, pago, contrato, visita, inmueble, cliente, propietario, usuario;

CREATE TABLE usuario (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(30) NOT NULL UNIQUE,
  nombres VARCHAR(100) NOT NULL,
  clave_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','agente') NOT NULL DEFAULT 'agente',
  estado ENUM('activo','bloqueado') NOT NULL DEFAULT 'activo',
  intentos TINYINT NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE propietario (
  id_propietario INT AUTO_INCREMENT PRIMARY KEY,
  nombres VARCHAR(120) NOT NULL,
  dni_ruc VARCHAR(11) NOT NULL UNIQUE,
  telefono VARCHAR(20) NULL,
  email VARCHAR(100) NULL
) ENGINE=InnoDB;

CREATE TABLE cliente (
  id_cliente INT AUTO_INCREMENT PRIMARY KEY,
  nombres VARCHAR(120) NOT NULL,
  dni_ruc VARCHAR(11) NOT NULL UNIQUE,
  telefono VARCHAR(20) NULL,
  email VARCHAR(100) NULL,
  tipo ENUM('comprador','inquilino') NOT NULL DEFAULT 'inquilino',
  presupuesto DECIMAL(12,2) NULL,
  consentimiento TINYINT(1) NOT NULL DEFAULT 0,          -- Ley 29733
  fecha_consentimiento DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE inmueble (
  id_inmueble INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(12) NOT NULL UNIQUE,
  tipo ENUM('casa','departamento','tienda','oficina','terreno','chacra') NOT NULL,
  operacion ENUM('venta','alquiler') NOT NULL,
  direccion VARCHAR(200) NOT NULL,
  distrito VARCHAR(60) NOT NULL,
  area_m2 DECIMAL(10,2) NOT NULL,
  precio DECIMAL(12,2) NOT NULL,
  partida_registral VARCHAR(30) NULL,
  estado ENUM('disponible','reservado','vendido','alquilado','baja') NOT NULL DEFAULT 'disponible',
  id_propietario INT NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_propietario) REFERENCES propietario(id_propietario),
  INDEX idx_busqueda (estado, tipo, operacion, distrito)
) ENGINE=InnoDB;

CREATE TABLE visita (
  id_visita INT AUTO_INCREMENT PRIMARY KEY,
  id_inmueble INT NOT NULL,
  id_cliente INT NOT NULL,
  id_usuario INT NOT NULL,
  fecha_hora DATETIME NOT NULL,
  estado ENUM('programada','realizada','cancelada') NOT NULL DEFAULT 'programada',
  observaciones VARCHAR(255) NULL,
  FOREIGN KEY (id_inmueble) REFERENCES inmueble(id_inmueble),
  FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB;

CREATE TABLE contrato (
  id_contrato INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(20) NOT NULL UNIQUE,
  id_inmueble INT NOT NULL,
  id_cliente INT NOT NULL,
  id_usuario INT NOT NULL,
  tipo ENUM('compraventa','alquiler') NOT NULL,
  fecha_firma DATE NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  garantia DECIMAL(12,2) NOT NULL DEFAULT 0,
  plazo_meses INT NULL,
  comision DECIMAL(12,2) NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_inmueble) REFERENCES inmueble(id_inmueble),
  FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB;

CREATE TABLE pago (
  id_pago INT AUTO_INCREMENT PRIMARY KEY,
  id_contrato INT NOT NULL,
  concepto ENUM('cuota','renta','garantia','comision') NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  estado ENUM('pendiente','pagado') NOT NULL DEFAULT 'pendiente',
  fecha_pago DATE NULL,
  nro_comprobante VARCHAR(30) NULL,
  FOREIGN KEY (id_contrato) REFERENCES contrato(id_contrato),
  INDEX idx_pago_estado (estado, fecha_vencimiento)
) ENGINE=InnoDB;

CREATE TABLE auditoria (
  id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NULL,
  accion VARCHAR(50) NOT NULL,
  detalle VARCHAR(255) NULL,
  ip VARCHAR(45) NULL,
  fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Datos de ejemplo (los usuarios se crean con setup.php)
INSERT INTO propietario (nombres, dni_ruc, telefono, email) VALUES
 ('Julia Mamani Condori', '45879632', '954111222', 'julia@example.com'),
 ('Inversiones Los Andes S.A.C.', '20456789012', '054222333', 'contacto@losandes.example');
INSERT INTO cliente (nombres, dni_ruc, telefono, email, tipo, presupuesto, consentimiento, fecha_consentimiento) VALUES
 ('Rosa Quispe Mamani', '71234567', '959333444', 'rosa@example.com', 'inquilino', 2000.00, 1, NOW()),
 ('Luis Ccori Huamán', '40123456', '958777888', 'luis@example.com', 'comprador', 450000.00, 1, NOW());
INSERT INTO inmueble (codigo, tipo, operacion, direccion, distrito, area_m2, precio, partida_registral, id_propietario) VALUES
 ('INM-0001','departamento','alquiler','Av. Ejército 450','Cayma',85,1800,NULL,1),
 ('INM-0002','departamento','alquiler','Calle Los Pinos 120','Cayma',70,1500,NULL,1),
 ('INM-0003','casa','venta','Calle Mercaderes 215','Yanahuara',180,420000,'11005678',2),
 ('INM-0004','terreno','venta','Fundo Santa Rita, sector 3','Characato',2500,180000,'11009876',2);
