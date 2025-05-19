<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('account_status', ['active', 'suspended', 'inactive'])->default('active');
            $table->bigInteger('storage_quota')->default(1073741824); // 1GB default
            $table->bigInteger('storage_used')->default(0);
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('last_active_at')->nullable();
            $table->string('profile_image_path', 255)->nullable();
            $table->rememberToken();
            $table->timestamps(); // includes created_at and updated_at
        });

        // User groups (for group-based sharing)
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('is_private')->default(true);
        });

        // Group members junction table
        Schema::create('group_members', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['member', 'admin', 'owner'])->default('member');
            $table->timestamp('joined_at')->useCurrent();
            
            $table->primary(['group_id', 'user_id']);
        });

        // Friendships table
        Schema::create('friendships', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'blocked', 'declined'])->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('responded_at')->nullable();
            
            $table->primary(['user_id', 'friend_id']);
            $table->index('status');
        });

        // Folders table
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('parent_folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('color', 7)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->index(['user_id', 'parent_folder_id']);
        });

        // Tags table
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->integer('usage_count')->default(0);
            $table->string('color', 7)->nullable();
        });

        // Documents table
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->integer('latest_version')->default(1);
            $table->boolean('is_public')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('purge_at')->nullable(); // For automatic deletion
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('last_accessed_at')->nullable();
            
            $table->index('owner_id');
            $table->index('is_public');
            $table->index('is_deleted');
            $table->index('expires_at');
            $table->index('is_favorite');
            
            // Create fulltext index for search
            $table->fullText(['title', 'description']);
        });

        // Document templates
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('file_path', 255);
            $table->string('file_type', 50);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_public')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // Document versions
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->string('file_type', 50)->nullable();
            $table->string('mime_type', 100)->nullable(); // More specific than file_type
            $table->bigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->enum('storage_provider', ['local', 's3', 'google', 'azure'])->default('local');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->text('change_summary')->nullable();
            $table->boolean('is_autosave')->default(false);
            
            $table->unique(['document_id', 'version_number']);
            $table->index('document_id');
            $table->index('uploaded_at');
        });

        // Document tags junction table
        Schema::create('document_tags', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamp('added_at')->useCurrent();
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            
            $table->primary(['document_id', 'tag_id']);
        });

        // Favorites
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            
            $table->primary(['user_id', 'document_id']);
        });

        // Document access logs
        Schema::create('document_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('document_versions')->nullOnDelete();
            $table->enum('action', ['view', 'download', 'delete', 'update', 'restore', 'share', 'preview', 'print']);
            $table->timestamp('occurred_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('device_type', 50)->nullable();
            
            $table->index(['document_id', 'user_id']);
            $table->index('occurred_at');
        });


 Schema::create('document_shares', function (Blueprint $table) {
    $table->id();
    $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
    $table->foreignId('shared_with_user')->nullable()->constrained('users')->cascadeOnDelete();
    $table->foreignId('shared_with_group')->nullable()->constrained('user_groups')->cascadeOnDelete();
    $table->enum('permission', ['view', 'comment', 'edit', 'owner'])->default('view');
    $table->foreignId('shared_by_user')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('shared_at')->useCurrent();
    $table->dateTime('expires_at')->nullable();
    $table->integer('access_count')->default(0);
    $table->text('message')->nullable();
    $table->boolean('watermark_enabled')->default(false);

    // Use a shorter unique index name to avoid MySQL error 1059
    $table->unique(
        ['document_id', 'shared_with_user', 'shared_with_group'],
        'doc_shares_docid_shareduser_sharedgroup_unique'
    );

    $table->index('expires_at');
});

DB::statement('ALTER TABLE document_shares ADD CONSTRAINT check_shared_with CHECK (shared_with_user IS NOT NULL OR shared_with_group IS NOT NULL)');


        // Public document links
        Schema::create('public_document_links', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->enum('permission', ['view', 'comment', 'edit'])->default('view');
            $table->string('password_hash', 255)->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('use_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('watermark_enabled')->default(false);
            
            $table->index('expires_at');
        });

        // Document comments
        Schema::create('document_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('document_versions')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_comment_id')->nullable()->constrained('document_comments')->nullOnDelete();
            $table->text('content');
            $table->json('position_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            
            $table->index(['document_id', 'is_deleted']);
        });

        // Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['share', 'comment', 'friend_request', 'version', 'mention', 'system', 'group_invite']);
            $table->string('entity_type', 50);
            $table->integer('entity_id');
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->string('action_url', 255)->nullable(); // Deep link for the notification
            
            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
        });

        // User devices (for push notifications)
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_token', 255);
            $table->enum('device_type', ['ios', 'android', 'web', 'desktop']);
            $table->string('device_name', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('last_used_at')->useCurrent();
            $table->boolean('is_active')->default(true);
            
            $table->unique(['user_id', 'device_token']);
            $table->index('device_token');
        });

        // User settings
        Schema::create('user_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->json('notification_preferences');
            $table->json('ui_preferences')->nullable();
            $table->enum('default_share_permission', ['view', 'comment', 'edit'])->default('view');
            $table->string('language', 10)->default('en-US');
            $table->string('timezone', 50)->default('UTC');
            $table->string('download_format', 10)->nullable(); // Preferred download format (original, pdf, etc.)
        });

        // Audit logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 50);
            $table->string('entity_type', 50)->nullable();
            $table->integer('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('location', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['user_id', 'action_type']);
            $table->index('created_at');
        });

        // Watermark templates
        Schema::create('watermark_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('text', 255)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->enum('position', ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'])->default('bottom-right');
            $table->integer('opacity')->default(50);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // Password reset tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sessions
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to avoid foreign key constraints
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('watermark_templates');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('document_comments');
        Schema::dropIfExists('public_document_links');
        Schema::dropIfExists('document_shares');
        Schema::dropIfExists('document_access_logs');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('document_tags');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('folders');
        Schema::dropIfExists('friendships');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('user_groups');
        Schema::dropIfExists('users');
    }
};