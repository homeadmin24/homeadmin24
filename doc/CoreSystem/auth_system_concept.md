# Authentication, Roles and User Management Concept

## 🎯 **Basic Requirements**

The homeadmin24 WEG management system needs a simple, database-driven authentication system with role-based access control for different user types managing property data.

## 📊 **Database Schema**

### **1. User Table**
```sql
CREATE TABLE user (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(180) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,  -- bcrypt hashed
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    INDEX idx_email (email),
    INDEX idx_active (is_active)
);
```

### **2. Role Table**
```sql
CREATE TABLE role (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### **3. User-Role Assignment Table**
```sql
CREATE TABLE user_role (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NULL,  -- user_id who assigned this role
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES role(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES user(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_role (user_id, role_id)
);
```

## 👥 **User Roles Definition**

### **1. Super Admin**
- **Role Name**: `ROLE_SUPER_ADMIN`
- **Permissions**: Full system access
- **Can Access**:
  - User management (create/edit/delete users)
  - Role assignment
  - System configuration
  - All WEG data management
  - Zahlungskategorie admin interface
  - Database backups and maintenance

### **2. WEG Administrator**
- **Role Name**: `ROLE_WEG_ADMIN`
- **Permissions**: Full WEG data management
- **Can Access**:
  - All WEG, Einheiten, Kostenkonto management
  - Zahlung and Rechnung CRUD operations
  - Hausgeldabrechnung generation
  - Dienstleister management
  - Document management
  - Financial reports

### **3. Accountant**
- **Role Name**: `ROLE_ACCOUNTANT`
- **Permissions**: Financial data focus
- **Can Access**:
  - Zahlung and Rechnung management
  - Hausgeldabrechnung generation and viewing
  - Financial reports
  - Kostenkonto viewing (read-only)
  - Document viewing

### **4. Property Manager**
- **Role Name**: `ROLE_PROPERTY_MANAGER`
- **Permissions**: Property-focused operations
- **Can Access**:
  - WEG and Einheiten management
  - Dienstleister management
  - Document management
  - Basic payment viewing (read-only)

### **5. Read-Only User**
- **Role Name**: `ROLE_VIEWER`
- **Permissions**: View-only access
- **Can Access**:
  - View all data (no editing)
  - Generate reports
  - Download documents

## 🔐 **Security Implementation**

### **1. Password Requirements**
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- bcrypt hashing with cost factor 12

### **2. Session Management**
- Session timeout: 4 hours of inactivity
- Remember me: 30 days (optional)
- Secure session cookies (httpOnly, secure, sameSite)

### **3. Login Security**
- Account lockout after 5 failed attempts
- Lockout duration: 15 minutes
- Login attempt logging

## 🚪 **Access Control Matrix**

| Feature | Super Admin | WEG Admin | Accountant | Property Manager | Viewer |
|---------|-------------|-----------|------------|------------------|--------|
| User Management | ✅ | ❌ | ❌ | ❌ | ❌ |
| WEG/Einheiten Edit | ✅ | ✅ | ❌ | ✅ | ❌ |
| Kostenkonto Edit | ✅ | ✅ | ❌ | ❌ | ❌ |
| Zahlung CRUD | ✅ | ✅ | ✅ | 👁️ | 👁️ |
| Rechnung CRUD | ✅ | ✅ | ✅ | ❌ | 👁️ |
| HGA Generation | ✅ | ✅ | ✅ | ❌ | ❌ |
| Dienstleister CRUD | ✅ | ✅ | ❌ | ✅ | 👁️ |
| Document Management | ✅ | ✅ | 👁️ | ✅ | 👁️ |
| System Config | ✅ | ❌ | ❌ | ❌ | ❌ |

**Legend**: ✅ = Full Access, 👁️ = Read-Only, ❌ = No Access

## 🛠 **Symfony Implementation**

### **1. User Entity**
```php
#[ORM\Entity]
#[UniqueEntity(fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'user_role')]
    private Collection $userRoles;

    // ... getters, setters, UserInterface methods
}
```

### **2. Role Entity**
```php
#[ORM\Entity]
class Role
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    private ?string $displayName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // ... getters, setters
}
```

### **3. Security Configuration**
```yaml
# config/packages/security.yaml
security:
    password_hashers:
        App\Entity\User:
            algorithm: bcrypt
            cost: 12

    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email

    firewalls:
        main:
            lazy: true
            provider: app_user_provider
            form_login:
                login_path: app_login
                check_path: app_login
                default_target_path: app_home
            logout:
                path: app_logout
                target: app_login
            remember_me:
                secret: '%kernel.secret%'
                lifetime: 2592000 # 30 days

    access_control:
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/admin, roles: ROLE_SUPER_ADMIN }
        - { path: ^/weg, roles: [ROLE_WEG_ADMIN, ROLE_PROPERTY_MANAGER] }
        - { path: ^/zahlung, roles: [ROLE_WEG_ADMIN, ROLE_ACCOUNTANT] }
        - { path: ^/, roles: ROLE_VIEWER }
```

## 📱 **User Interface Components**

### **1. Login Page** (`/login`)
- Email/password form
- Remember me checkbox
- Password reset link
- Account lockout notification

### **2. User Management** (`/admin/users`) - Super Admin Only
- User list with search/filter
- Create new user form
- Edit user details
- Role assignment interface
- Activate/deactivate users

### **3. Profile Management** (`/profile`)
- Change password
- Update personal information
- View assigned roles
- Login history

### **4. Navigation Security**
- Role-based menu visibility
- Button/link permissions
- Form field restrictions
- Page access validation

## 🔄 **Initial Data Setup**

### **1. Default Roles**
```sql
INSERT INTO role (name, display_name, description) VALUES
('ROLE_SUPER_ADMIN', 'Super Administrator', 'Full system access and user management'),
('ROLE_WEG_ADMIN', 'WEG Administrator', 'Complete WEG data management'),
('ROLE_ACCOUNTANT', 'Accountant', 'Financial data and reporting'),
('ROLE_PROPERTY_MANAGER', 'Property Manager', 'Property and service provider management'),
('ROLE_VIEWER', 'Viewer', 'Read-only access to all data');
```

### **2. Default Super Admin User**
```php
// Command: php bin/console app:create-admin
$user = new User();
$user->setEmail('admin@hausman.local');
$user->setPassword($passwordHasher->hashPassword($user, 'admin123'));
$user->setFirstName('System');
$user->setLastName('Administrator');
$user->addRole($superAdminRole);
```

## 🚀 **Implementation Phases**

### **Phase 1: Basic Authentication**
- User entity and authentication
- Login/logout functionality
- Password hashing and validation

### **Phase 2: Role System**
- Role entity and user-role relationships
- Basic access control
- Role-based navigation

### **Phase 3: Advanced Security**
- Account lockout mechanism
- Session management
- Password reset functionality

### **Phase 4: User Management Interface**
- Admin user management
- Role assignment interface
- User profile management

### **Phase 5: Fine-grained Permissions**
- Method-level security
- Field-level access control
- Advanced role configurations

## 📊 **Monitoring and Logging**

### **1. Authentication Events**
- Login attempts (success/failure)
- Account lockouts
- Password changes
- Role assignments

### **2. Access Control Events**
- Unauthorized access attempts
- Permission denied events
- Administrative actions

### **3. User Activity**
- Last login tracking
- Feature usage statistics
- Data modification logs

This authentication system provides a solid foundation for the homeadmin24 WEG management system with appropriate security levels and role-based access control.