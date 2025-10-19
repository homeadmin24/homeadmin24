# Document Management & Cloud Migration Strategy

## Document Management Options Analysis

### 1. Local File Storage (Simplest)
```
/data/dokumente/
├── rechnungen/
│   ├── 2024/
│   │   ├── rechnung_123_wartung.pdf
│   │   └── rechnung_124_reinigung.pdf
├── vertraege/
├── protokolle/
└── sonstiges/
```
**Pros:** Simple, no external dependencies, works immediately
**Cons:** No version control, backup responsibility, file access management

### 2. Google Drive Integration (Current)
```php
// Using Google Drive API
$service = new Google_Service_Drive($client);
$file = $service->files->get($fileId);
```
**Pros:** Already in use, free, automatic backup, sharing capabilities
**Cons:** API complexity, authentication management, rate limits

### 3. MinIO (Object Storage)
```docker
docker run -p 9000:9000 -p 9001:9001 \
  -e "MINIO_ROOT_USER=admin" \
  -e "MINIO_ROOT_PASSWORD=password" \
  minio/minio server /data --console-address ":9001"
```
**Pros:** S3-compatible, professional, versioning, metadata
**Cons:** Additional infrastructure, overkill for small WEG

## Recommendation: Start Simple, Evolve

### Phase 1: Local File Storage + Database References
```sql
CREATE TABLE dokument (
    id INT PRIMARY KEY,
    dateiname VARCHAR(255),
    dateipfad VARCHAR(500),
    dateityp VARCHAR(50),
    dategroesse INT,
    upload_datum DATETIME,
    kategorie VARCHAR(100),
    beschreibung TEXT,
    rechnung_id INT NULLABLE,
    dienstleister_id INT NULLABLE
);
```

### Phase 2: File Management Entity
```php
class Dokument {
    private string $dateiname;
    private string $dateipfad;
    private string $dateityp;
    private int $dategroesse;
    private \DateTime $uploadDatum;
    private string $kategorie;
    private ?Rechnung $rechnung = null;
    private ?Dienstleister $dienstleister = null;
}
```

### Implementation Strategy

#### Immediate (Local Files):
```
Public/hausman/data/dokumente/
├── rechnungen/
├── vertraege/
├── protokolle/
└── uploads/
```

#### Later (Google Drive Connector):
```php
class GoogleDriveService {
    public function uploadFile($localPath, $folderId);
    public function downloadFile($fileId, $localPath);
    public function listFiles($folderId);
    public function createFolder($name, $parentId);
}
```

## Quick Start Recommendation

### Option A: Local Files (Start Now)
- Create `/data/dokumente/` folder structure
- Build Dokument CRUD with file upload
- Reference from Rechnung/Dienstleister tables
- **Time: 2-3 hours**

### Option B: Google Drive Integration (More Complex)
- Use Google Drive API with service account
- Store Drive file IDs in database
- Stream files through your app
- **Time: 1-2 days**

### Option C: Hybrid Approach
- Start with local files for development
- Add Google Drive sync later
- Keep file paths abstracted in database
- **Time: Start immediately, evolve over time**

---

# Local to MinIO Migration Strategy

## Strategy: Abstract Storage Layer

### 1. Storage Interface Pattern
```php
// src/Service/Storage/StorageInterface.php
interface StorageInterface
{
    public function upload(string $path, $content): string;
    public function download(string $path): string;
    public function delete(string $path): bool;
    public function exists(string $path): bool;
    public function getUrl(string $path): string;
}
```

### 2. Local Storage Implementation
```php
// src/Service/Storage/LocalStorage.php
class LocalStorage implements StorageInterface
{
    private string $basePath;
    
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }
    
    public function upload(string $path, $content): string
    {
        $fullPath = $this->basePath . '/' . $path;
        file_put_contents($fullPath, $content);
        return $path;
    }
    
    public function download(string $path): string
    {
        return file_get_contents($this->basePath . '/' . $path);
    }
    
    public function getUrl(string $path): string
    {
        return '/uploads/' . $path; // Served by Symfony
    }
}
```

### 3. MinIO Storage Implementation
```php
// src/Service/Storage/MinIOStorage.php
use Aws\S3\S3Client;

class MinIOStorage implements StorageInterface
{
    private S3Client $client;
    private string $bucket;
    
    public function __construct(S3Client $client, string $bucket)
    {
        $this->client = $client;
        $this->bucket = $bucket;
    }
    
    public function upload(string $path, $content): string
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
            'Body' => $content,
        ]);
        return $path;
    }
    
    public function download(string $path): string
    {
        $result = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ]);
        return $result['Body']->getContents();
    }
    
    public function getUrl(string $path): string
    {
        return $this->client->getObjectUrl($this->bucket, $path);
    }
}
```

### 4. Configuration-Based Service
```yaml
# config/services.yaml
parameters:
    storage.type: '%env(STORAGE_TYPE)%'  # 'local' or 'minio'
    storage.local.path: '%kernel.project_dir%/data/dokumente'
    storage.minio.endpoint: '%env(MINIO_ENDPOINT)%'
    storage.minio.bucket: '%env(MINIO_BUCKET)%'

services:
    # Local Storage
    App\Service\Storage\LocalStorage:
        arguments:
            $basePath: '%storage.local.path%'
        tags: ['storage.implementation']
    
    # MinIO Storage (when needed)
    App\Service\Storage\MinIOStorage:
        arguments:
            $client: '@minio.client'
            $bucket: '%storage.minio.bucket%'
        tags: ['storage.implementation']
    
    # Factory that chooses implementation
    App\Service\Storage\StorageInterface:
        factory: ['@App\Service\Storage\StorageFactory', 'create']
        arguments: ['%storage.type%']
```

### 5. Environment Configuration
```bash
# .env (Local Development)
STORAGE_TYPE=local

# .env (Production with MinIO)  
STORAGE_TYPE=minio
MINIO_ENDPOINT=localhost:9000
MINIO_ACCESS_KEY=admin
MINIO_SECRET_KEY=password
MINIO_BUCKET=weg-documents
```

### 6. Dokument Entity (Storage-Agnostic)
```php
// src/Entity/Dokument.php
class Dokument
{
    private string $dateiname;
    private string $speicherPfad;  // Abstract path (same for both)
    private string $dateityp;
    private int $dategroesse;
    private \DateTime $uploadDatum;
    private string $kategorie;
    
    // Relationships
    private ?Rechnung $rechnung = null;
    private ?Dienstleister $dienstleister = null;
}
```

### 7. Document Service
```php
// src/Service/DocumentService.php
class DocumentService
{
    public function __construct(
        private StorageInterface $storage,
        private EntityManagerInterface $em
    ) {}
    
    public function uploadDocument(
        UploadedFile $file, 
        string $kategorie,
        ?Rechnung $rechnung = null
    ): Dokument {
        // Generate storage path
        $path = sprintf(
            '%s/%s/%s',
            $kategorie,
            date('Y/m'),
            uniqid() . '.' . $file->getClientOriginalExtension()
        );
        
        // Upload to current storage
        $this->storage->upload($path, $file->getContent());
        
        // Save metadata to database
        $dokument = new Dokument();
        $dokument->setDateiname($file->getClientOriginalName());
        $dokument->setSpeicherPfad($path);
        $dokument->setRechnung($rechnung);
        // ... set other properties
        
        $this->em->persist($dokument);
        $this->em->flush();
        
        return $dokument;
    }
}
```

## Migration Process

### Step 1: Current State (Local)
```bash
STORAGE_TYPE=local
```
Files stored in: `/data/dokumente/`

### Step 2: Setup MinIO
```bash
# Start MinIO container
docker run -d \
  --name minio \
  -p 9000:9000 -p 9001:9001 \
  -e "MINIO_ROOT_USER=admin" \
  -e "MINIO_ROOT_PASSWORD=password123" \
  -v minio_data:/data \
  minio/minio server /data --console-address ":9001"
```

### Step 3: Migration Script
```php
// src/Command/MigrateStorageCommand.php
class MigrateStorageCommand extends Command
{
    public function execute(): int
    {
        $localStorage = new LocalStorage('/data/dokumente');
        $minioStorage = new MinIOStorage($s3Client, 'weg-documents');
        
        $documents = $this->dokumentRepository->findAll();
        
        foreach ($documents as $dokument) {
            // Download from local
            $content = $localStorage->download($dokument->getSpeicherPfad());
            
            // Upload to MinIO
            $minioStorage->upload($dokument->getSpeicherPfad(), $content);
            
            $this->output->writeln("Migrated: " . $dokument->getDateiname());
        }
        
        return Command::SUCCESS;
    }
}
```

### Step 4: Switch Configuration
```bash
# Change .env
STORAGE_TYPE=minio
MINIO_ENDPOINT=localhost:9000
MINIO_ACCESS_KEY=admin
MINIO_SECRET_KEY=password123
MINIO_BUCKET=weg-documents
```

### Step 5: Run Migration
```bash
php bin/console app:migrate-storage
```

## Implementation Order

1. **Today**: Implement abstract storage interface with local storage
2. **Next week**: Add MinIO classes (but keep using local)
3. **When ready**: Switch environment variable + run migration
4. **Cleanup**: Remove local files after verification

## Benefits of This Approach

✅ **Zero code changes** in controllers/templates  
✅ **Database stays the same** (just storage paths)  
✅ **Easy rollback** (change environment variable)  
✅ **Gradual migration** (test MinIO before switching)  
✅ **Future-proof** (can add Google Drive later)  

## Recommendation

**Start with Option A (Local Files)** because:
1. ✅ You can implement it today
2. ✅ No external API dependencies  
3. ✅ Easy to test and develop
4. ✅ Can migrate to Google Drive later
5. ✅ Matches your current data structure pattern

The abstract storage layer ensures you can switch between local storage, MinIO, or even Google Drive later without changing any business logic code.