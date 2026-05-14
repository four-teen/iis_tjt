CREATE TABLE IF NOT EXISTS accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(120) NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'Administrator',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    password_hash VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounts_username (username),
    UNIQUE KEY uq_accounts_email (email),
    KEY idx_accounts_role (role),
    KEY idx_accounts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_role_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_account_role_assignments_account_role (account_id, role),
    KEY idx_account_role_assignments_role (role),
    CONSTRAINT fk_account_role_assignments_account
        FOREIGN KEY (account_id) REFERENCES accounts (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_account_id BIGINT UNSIGNED NULL,
    account_id BIGINT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_account_activity_actor (actor_account_id),
    KEY idx_account_activity_account (account_id),
    KEY idx_account_activity_action (action),
    CONSTRAINT fk_activity_actor_account
        FOREIGN KEY (actor_account_id) REFERENCES accounts (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_activity_target_account
        FOREIGN KEY (account_id) REFERENCES accounts (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblemployees (
    employee_id INT(11) NOT NULL AUTO_INCREMENT,
    firstname VARCHAR(20) NOT NULL,
    middlename VARCHAR(20) NOT NULL,
    lastname VARCHAR(20) NOT NULL,
    who_is VARCHAR(1) NOT NULL,
    PRIMARY KEY (employee_id),
    KEY idx_tblemployees_name (lastname, firstname),
    KEY idx_tblemployees_who_is (who_is)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblcustomer (
    customerid INT(11) NOT NULL AUTO_INCREMENT,
    soa VARCHAR(7) NOT NULL,
    customername VARCHAR(100) NOT NULL,
    customeraddress VARCHAR(300) NOT NULL,
    status INT(11) NOT NULL,
    PRIMARY KEY (customerid),
    KEY idx_tblcustomer_soa (soa),
    KEY idx_tblcustomer_customername (customername),
    KEY idx_tblcustomer_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbllocation (
    locationid INT(11) NOT NULL AUTO_INCREMENT,
    location VARCHAR(120) NOT NULL,
    status INT(11) NOT NULL DEFAULT 1,
    PRIMARY KEY (locationid),
    KEY idx_tbllocation_location (location),
    KEY idx_tbllocation_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbldeliverytype (
    deliverytypeid INT(11) NOT NULL AUTO_INCREMENT,
    deliverytype VARCHAR(60) NOT NULL,
    PRIMARY KEY (deliverytypeid),
    KEY idx_tbldeliverytype_deliverytype (deliverytype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbltrucktype (
    trucktypeid INT(11) NOT NULL AUTO_INCREMENT,
    trucktype VARCHAR(50) NOT NULL,
    PRIMARY KEY (trucktypeid),
    KEY idx_tbltrucktype_trucktype (trucktype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblmake (
    makeid INT(11) NOT NULL AUTO_INCREMENT,
    makename VARCHAR(25) NOT NULL,
    PRIMARY KEY (makeid),
    KEY idx_tblmake_makename (makename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblvantype (
    vantypeid INT(11) NOT NULL AUTO_INCREMENT,
    vantype VARCHAR(50) NOT NULL,
    PRIMARY KEY (vantypeid),
    KEY idx_tblvantype_vantype (vantype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblbody (
    body_id INT(11) NOT NULL AUTO_INCREMENT,
    body_name VARCHAR(50) NOT NULL,
    PRIMARY KEY (body_id),
    KEY idx_tblbody_body_name (body_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblcolor (
    color_id INT(11) NOT NULL AUTO_INCREMENT,
    color_name VARCHAR(30) NOT NULL,
    PRIMARY KEY (color_id),
    KEY idx_tblcolor_color_name (color_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblcolor_plate (
    color_plate_id INT(11) NOT NULL AUTO_INCREMENT,
    color_plate_desc VARCHAR(70) NOT NULL,
    PRIMARY KEY (color_plate_id),
    KEY idx_tblcolor_plate_desc (color_plate_desc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblltfrb_status (
    ltfrb_status_id INT(11) NOT NULL AUTO_INCREMENT,
    ltfrb_status VARCHAR(25) NOT NULL,
    PRIMARY KEY (ltfrb_status_id),
    KEY idx_tblltfrb_status (ltfrb_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblfleet (
    fleetid INT(11) NOT NULL AUTO_INCREMENT,
    platenumber VARCHAR(20) NOT NULL,
    casenumber VARCHAR(20) NULL,
    validity DATE NULL,
    paremarks VARCHAR(25) NULL,
    pavalidity DATE NULL,
    decisionremarks VARCHAR(30) NULL,
    decisionvalidity DATE NULL,
    PRIMARY KEY (fleetid),
    UNIQUE KEY uq_tblfleet_platenumber (platenumber),
    KEY idx_tblfleet_validity (validity),
    KEY idx_tblfleet_paremarks (paremarks)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblfleet_info_1 (
    fleet_info_1_id INT(11) NOT NULL AUTO_INCREMENT,
    fleetid INT(11) NOT NULL,
    cpc VARCHAR(30) NULL,
    cpcvalidity DATE NULL,
    platecolor INT(11) NULL,
    ltfrbstatus INT(11) NULL,
    trucktype INT(11) NULL,
    vantype INT(11) NULL,
    make INT(11) NULL,
    body INT(11) NULL,
    color INT(11) NULL,
    yearmodel VARCHAR(4) NULL,
    yearacquired VARCHAR(4) NULL,
    chassisnumber VARCHAR(20) NULL,
    enginenumber VARCHAR(20) NULL,
    PRIMARY KEY (fleet_info_1_id),
    UNIQUE KEY uq_tblfleet_info_1_fleet (fleetid),
    KEY idx_tblfleet_info_trucktype (trucktype),
    KEY idx_tblfleet_info_make (make),
    KEY idx_tblfleet_info_vantype (vantype),
    KEY idx_tblfleet_info_body (body),
    CONSTRAINT fk_tblfleet_info_1_fleet
        FOREIGN KEY (fleetid) REFERENCES tblfleet (fleetid)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tblfleet_info_1_platecolor
        FOREIGN KEY (platecolor) REFERENCES tblcolor_plate (color_plate_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tblfleet_info_1_ltfrbstatus
        FOREIGN KEY (ltfrbstatus) REFERENCES tblltfrb_status (ltfrb_status_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tblfleet_info_1_trucktype
        FOREIGN KEY (trucktype) REFERENCES tbltrucktype (trucktypeid)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tblfleet_info_1_vantype
        FOREIGN KEY (vantype) REFERENCES tblvantype (vantypeid)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tblfleet_info_1_make
        FOREIGN KEY (make) REFERENCES tblmake (makeid)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tblfleet_info_1_body
        FOREIGN KEY (body) REFERENCES tblbody (body_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tblfleet_info_1_color
        FOREIGN KEY (color) REFERENCES tblcolor (color_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblfleet_assigned_driver_helper (
    assigned_id INT(11) NOT NULL AUTO_INCREMENT,
    assigned_fleetid INT(11) NOT NULL,
    assigned_employeeid INT(11) NOT NULL,
    PRIMARY KEY (assigned_id),
    UNIQUE KEY uq_tblfleet_assignment (assigned_fleetid, assigned_employeeid),
    KEY idx_tblfleet_assignment_employee (assigned_employeeid),
    CONSTRAINT fk_tblfleet_assignment_fleet
        FOREIGN KEY (assigned_fleetid) REFERENCES tblfleet (fleetid)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tblfleet_assignment_employee
        FOREIGN KEY (assigned_employeeid) REFERENCES tblemployees (employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblbooking (
    bookingidauto INT(11) NOT NULL AUTO_INCREMENT,
    bookingid BIGINT NOT NULL,
    bookingdate DATETIME NOT NULL,
    customername INT(11) NOT NULL,
    companyrepresentative VARCHAR(80) NOT NULL,
    origindestination INT(11) NOT NULL,
    pickupdate DATETIME NOT NULL,
    deliverydate DATETIME NOT NULL,
    deliverytype INT(11) NOT NULL,
    reservedplate INT(11) NOT NULL,
    PRIMARY KEY (bookingidauto),
    KEY idx_tblbooking_reference (bookingid),
    KEY idx_tblbooking_customer (customername),
    KEY idx_tblbooking_route (origindestination),
    KEY idx_tblbooking_pickup (pickupdate),
    KEY idx_tblbooking_reservedplate (reservedplate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblbooking_canceled (
    bookingidauto INT(11) NOT NULL,
    bookingid BIGINT NOT NULL,
    bookingdate DATETIME NOT NULL,
    customername INT(11) NOT NULL,
    companyrepresentative VARCHAR(80) NOT NULL,
    origindestination INT(11) NOT NULL,
    pickupdate DATETIME NOT NULL,
    deliverydate DATETIME NOT NULL,
    deliverytype INT(11) NOT NULL,
    reservedplate INT(11) NOT NULL,
    PRIMARY KEY (bookingidauto),
    KEY idx_tblbooking_canceled_reference (bookingid),
    KEY idx_tblbooking_canceled_customer (customername),
    KEY idx_tblbooking_canceled_pickup (pickupdate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblshipment_number (
    shipment_number_id INT(11) NOT NULL AUTO_INCREMENT,
    sn_reference_id BIGINT NOT NULL,
    shipmentnumber VARCHAR(60) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (shipment_number_id),
    UNIQUE KEY uq_tblshipment_number_reference (sn_reference_id),
    KEY idx_tblshipment_number_value (shipmentnumber)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblcoord_dispatch_preparations (
    prep_id INT(11) NOT NULL AUTO_INCREMENT,
    prep_referenceid BIGINT NOT NULL,
    prep_coordinator BIGINT UNSIGNED NULL,
    prep_customer_name_id INT(11) NOT NULL,
    prep_ods INT(11) NOT NULL,
    prep_plaka INT(11) NOT NULL,
    prep_dispatched_date DATETIME NOT NULL,
    prep_status VARCHAR(20) NOT NULL DEFAULT 'prepared',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (prep_id),
    UNIQUE KEY uq_tblcoord_dispatch_preparations_reference (prep_referenceid),
    KEY idx_tblcoord_dispatch_preparations_status (prep_status),
    KEY idx_tblcoord_dispatch_preparations_plate (prep_plaka),
    KEY idx_tblcoord_dispatch_preparations_dispatch_date (prep_dispatched_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbldispatched (
    dispatched_id INT(11) NOT NULL AUTO_INCREMENT,
    dis_referenceid BIGINT NOT NULL,
    dis_coordinator BIGINT UNSIGNED NULL,
    dis_customer_name_id INT(11) NOT NULL,
    dis_ods INT(11) NOT NULL,
    dis_plaka INT(11) NOT NULL,
    dis_dispatched_date DATETIME NOT NULL,
    dis_approved TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (dispatched_id),
    UNIQUE KEY uq_tbldispatched_reference (dis_referenceid),
    KEY idx_tbldispatched_plate (dis_plaka),
    KEY idx_tbldispatched_dispatch_date (dis_dispatched_date),
    KEY idx_tbldispatched_customer (dis_customer_name_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbldispatch_driver (
    dispatch_driver_auto_id INT(11) NOT NULL AUTO_INCREMENT,
    dispatch_reference_id BIGINT NOT NULL,
    dispatch_driver INT(11) NOT NULL,
    dispatch_date DATETIME NULL,
    dispatch_customer INT(11) NOT NULL,
    coordinator BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (dispatch_driver_auto_id),
    UNIQUE KEY uq_tbldispatch_driver_reference_driver (dispatch_reference_id, dispatch_driver),
    KEY idx_tbldispatch_driver_driver (dispatch_driver),
    KEY idx_tbldispatch_driver_reference (dispatch_reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbldispatch_helper (
    dispatch_helper_auto_id INT(11) NOT NULL AUTO_INCREMENT,
    dispatch_reference_id BIGINT NOT NULL,
    dispatch_helper INT(11) NOT NULL,
    dispatch_date DATETIME NULL,
    dispatch_customer INT(11) NOT NULL,
    coordinator BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (dispatch_helper_auto_id),
    UNIQUE KEY uq_tbldispatch_helper_reference_helper (dispatch_reference_id, dispatch_helper),
    KEY idx_tbldispatch_helper_helper (dispatch_helper),
    KEY idx_tbldispatch_helper_reference (dispatch_reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblcustomerinformation (
    customerinformationid INT(11) NOT NULL AUTO_INCREMENT,
    customerid INT(11) NOT NULL,
    origin VARCHAR(70) NOT NULL,
    destination VARCHAR(70) NOT NULL,
    driversrate DOUBLE NOT NULL,
    helpersrate DOUBLE NOT NULL,
    deliveryrate DOUBLE NOT NULL,
    deliverytype INT(11) NOT NULL,
    trucktype INT(11) NOT NULL,
    PRIMARY KEY (customerinformationid),
    KEY idx_tblcustomerinformation_customerid (customerid),
    KEY idx_tblcustomerinformation_origin (origin),
    KEY idx_tblcustomerinformation_destination (destination),
    KEY idx_tblcustomerinformation_deliverytype (deliverytype),
    KEY idx_tblcustomerinformation_trucktype (trucktype),
    CONSTRAINT fk_tblcustomerinformation_deliverytype
        FOREIGN KEY (deliverytype) REFERENCES tbldeliverytype (deliverytypeid)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tblcustomerinformation_trucktype
        FOREIGN KEY (trucktype) REFERENCES tbltrucktype (trucktypeid)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblbudget_od (
    od_budget_id INT(11) NOT NULL AUTO_INCREMENT,
    od_customerinformationid INT(11) NOT NULL,
    od_budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    od_budget_status TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (od_budget_id),
    UNIQUE KEY uq_tblbudget_od_route (od_customerinformationid),
    KEY idx_tblbudget_od_status (od_budget_status),
    CONSTRAINT fk_tblbudget_od_route
        FOREIGN KEY (od_customerinformationid) REFERENCES tblcustomerinformation (customerinformationid)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tblbudget_od_created_by
        FOREIGN KEY (created_by) REFERENCES accounts (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tblbudget_od_updated_by
        FOREIGN KEY (updated_by) REFERENCES accounts (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbldispatch_budget (
    dis_budget_id INT(11) NOT NULL AUTO_INCREMENT,
    dis_budget_referenceid BIGINT NOT NULL,
    dis_budget_acc_name INT(11) NOT NULL,
    dis_budget_platenum INT(11) NOT NULL,
    dis_budget_od INT(11) NOT NULL,
    dis_budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    dis_budget_dated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    remarks VARCHAR(100) NOT NULL DEFAULT 'Regular',
    created_by BIGINT UNSIGNED NULL,
    voided_at DATETIME NULL,
    voided_by BIGINT UNSIGNED NULL,
    void_reason VARCHAR(160) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (dis_budget_id),
    KEY idx_tbldispatch_budget_reference (dis_budget_referenceid),
    KEY idx_tbldispatch_budget_employee (dis_budget_acc_name),
    KEY idx_tbldispatch_budget_route (dis_budget_od),
    KEY idx_tbldispatch_budget_voided (voided_at),
    CONSTRAINT fk_tbldispatch_budget_employee
        FOREIGN KEY (dis_budget_acc_name) REFERENCES tblemployees (employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tbldispatch_budget_fleet
        FOREIGN KEY (dis_budget_platenum) REFERENCES tblfleet (fleetid)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tbldispatch_budget_route
        FOREIGN KEY (dis_budget_od) REFERENCES tblcustomerinformation (customerinformationid)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tbldispatch_budget_created_by
        FOREIGN KEY (created_by) REFERENCES accounts (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tbldispatch_budget_voided_by
        FOREIGN KEY (voided_by) REFERENCES accounts (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblowner_budget (
    owner_budget_id INT(11) NOT NULL AUTO_INCREMENT,
    owners_id INT(11) NOT NULL,
    date_released DATE NOT NULL,
    budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    deleted CHAR(1) NOT NULL DEFAULT 'N',
    created_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (owner_budget_id),
    KEY idx_tblowner_budget_owner (owners_id),
    KEY idx_tblowner_budget_deleted (deleted),
    CONSTRAINT fk_tblowner_budget_owner
        FOREIGN KEY (owners_id) REFERENCES tblemployees (employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tblowner_budget_created_by
        FOREIGN KEY (created_by) REFERENCES accounts (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tblowner_budget_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES accounts (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblcustomerinformation_new_rates (
    customerinformationid INT(11) NOT NULL,
    driversrate DOUBLE NOT NULL,
    helpersrate DOUBLE NOT NULL,
    deliveryrate DOUBLE NOT NULL,
    deliverytype INT(11) NULL,
    trucktype INT(11) NULL,
    PRIMARY KEY (customerinformationid),
    KEY idx_tblcustomerinformation_new_rates_deliverytype (deliverytype),
    KEY idx_tblcustomerinformation_new_rates_trucktype (trucktype),
    CONSTRAINT fk_tblcustomerinformation_new_rates_deliverytype
        FOREIGN KEY (deliverytype) REFERENCES tbldeliverytype (deliverytypeid)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tblcustomerinformation_new_rates_trucktype
        FOREIGN KEY (trucktype) REFERENCES tbltrucktype (trucktypeid)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbltripdrops_perdrops (
    perdrops_id INT(11) NOT NULL AUTO_INCREMENT,
    perdrops_referenceid INT(11) NOT NULL,
    perdrops_locationid INT(11) NOT NULL,
    perdrops_rate VARCHAR(19) NOT NULL,
    perdrops_server_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (perdrops_id),
    KEY idx_tbltripdrops_perdrops_location (perdrops_locationid),
    CONSTRAINT fk_tbltripdrops_perdrops_perdrops_locationid
        FOREIGN KEY (perdrops_locationid) REFERENCES tbllocation (locationid)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbltripdrops_perkilo (
    perkilo_id INT(11) NOT NULL AUTO_INCREMENT,
    perkilo_referenceid INT(11) NOT NULL,
    perkilo_locationid INT(11) NOT NULL,
    numsack VARCHAR(9) NOT NULL,
    kilo_persack VARCHAR(9) NOT NULL,
    perkilo_server_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (perkilo_id),
    KEY idx_tbltripdrops_perkilo_location (perkilo_locationid),
    CONSTRAINT fk_tbltripdrops_perkilo_perkilo_locationid
        FOREIGN KEY (perkilo_locationid) REFERENCES tbllocation (locationid)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblmultiple_pickup (
    mpu_id INT(11) NOT NULL AUTO_INCREMENT,
    mpu_referenceid INT(11) NOT NULL,
    mpu_fleetid INT(11) NOT NULL,
    mpu_locationid INT(11) NOT NULL,
    mpu_rate VARCHAR(19) NOT NULL,
    PRIMARY KEY (mpu_id),
    KEY idx_tblmultiple_pickup_fleet (mpu_fleetid),
    KEY idx_tblmultiple_pickup_location (mpu_locationid),
    CONSTRAINT fk_tblmultiple_pickup_mpu_locationid
        FOREIGN KEY (mpu_locationid) REFERENCES tbllocation (locationid)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbladditional_trips (
    add_trips_id INT(11) NOT NULL AUTO_INCREMENT,
    add_trip_reference_id INT(11) NOT NULL,
    add_trip_fleetid INT(11) NOT NULL,
    add_trip_date_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    add_trip_customer_od INT(11) NOT NULL,
    PRIMARY KEY (add_trips_id),
    KEY idx_tbladditional_trips_fleet (add_trip_fleetid),
    KEY idx_tbladditional_trips_customer_od (add_trip_customer_od),
    CONSTRAINT fk_tbladditional_trips_add_trip_customer_od
        FOREIGN KEY (add_trip_customer_od) REFERENCES tblcustomerinformation (customerinformationid)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
