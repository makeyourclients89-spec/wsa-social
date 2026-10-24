<?php
declare(strict_types=1);

// ----------------------------------------------------------------------
// WSA Multi-Channel Publisher - Database Connection & Migrations
// ----------------------------------------------------------------------

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_sqlite')) {
        http_response_code(500);
        exit('<h2>PDO SQLite extension is required.</h2><p>Enable <code>extension=pdo_sqlite</code> and <code>extension=sqlite3</code> in php.ini, then restart Apache.</p>');
    }

    $storageDir = dirname(DB_FILE);
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }
    if (defined('UPLOAD_DIR') && !is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $pdo->exec('PRAGMA synchronous = NORMAL;');

    $version = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version < 2) {
        bootstrap_db($pdo);
        $pdo->exec('PRAGMA user_version = 2;');
    }
    if ($version < 3) {
        $cols = $pdo->query("PRAGMA table_info(campaigns)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('media_path', $cols, true)) {
            $pdo->exec("ALTER TABLE campaigns ADD COLUMN media_path TEXT;");
        }
        $pdo->exec('PRAGMA user_version = 3;');
    }
    if ($version < 4) {
        // Version 4: System settings table & Meta OAuth fields
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings(
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
        ");

        $acctCols = $pdo->query("PRAGMA table_info(accounts)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('access_token', $acctCols, true)) {
            $pdo->exec("ALTER TABLE accounts ADD COLUMN access_token TEXT;");
        }
        if (!in_array('token_expires_at', $acctCols, true)) {
            $pdo->exec("ALTER TABLE accounts ADD COLUMN token_expires_at TEXT;");
        }
        if (!in_array('meta_page_id', $acctCols, true)) {
            $pdo->exec("ALTER TABLE accounts ADD COLUMN meta_page_id TEXT;");
        }
        if (!in_array('meta_ig_id', $acctCols, true)) {
            $pdo->exec("ALTER TABLE accounts ADD COLUMN meta_ig_id TEXT;");
        }

        $clientCols = $pdo->query("PRAGMA table_info(clients)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('is_active', $clientCols, true)) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1;");
        }

        $pdo->exec('PRAGMA user_version = 4;');
    }
    if ($version < 5) {
        // Version 5: LinkedIn OAuth fields and Unified Comments Log Table
        $acctCols = $pdo->query("PRAGMA table_info(accounts)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('refresh_token', $acctCols, true)) {
            $pdo->exec("ALTER TABLE accounts ADD COLUMN refresh_token TEXT;");
        }
        if (!in_array('linkedin_urn', $acctCols, true)) {
            $pdo->exec("ALTER TABLE accounts ADD COLUMN linkedin_urn TEXT;");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comments(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                account_id INTEGER,
                platform TEXT NOT NULL,
                post_external_id TEXT NOT NULL,
                comment_text TEXT NOT NULL,
                response_id TEXT,
                status TEXT NOT NULL DEFAULT 'published',
                error_message TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY(account_id) REFERENCES accounts(id) ON DELETE SET NULL
            );
            CREATE INDEX IF NOT EXISTS idx_comments_client ON comments(client_id);
            CREATE INDEX IF NOT EXISTS idx_comments_platform ON comments(platform, post_external_id);
        ");

        $pdo->exec('PRAGMA user_version = 5;');
    }
    if ($version < 6) {
        // Version 6: Multi-Account targeting & Workspace Isolation
        $jobCols = $pdo->query("PRAGMA table_info(jobs)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('account_id', $jobCols, true)) {
            $pdo->exec("ALTER TABLE jobs ADD COLUMN account_id INTEGER;");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_jobs_account_id ON jobs(account_id);");
        }

        $cmpCols = $pdo->query("PRAGMA table_info(campaigns)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('target_accounts', $cmpCols, true)) {
            $pdo->exec("ALTER TABLE campaigns ADD COLUMN target_accounts TEXT;");
        }

        $acctCols = $pdo->query("PRAGMA table_info(accounts)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('avatar_url', $acctCols, true)) {
            $pdo->exec("ALTER TABLE accounts ADD COLUMN avatar_url TEXT;");
        }
        if (!in_array('page_category', $acctCols, true)) {
            $pdo->exec("ALTER TABLE accounts ADD COLUMN page_category TEXT;");
        }

        // Seed Faizan workspace and multi-accounts
        $chkFaizan = $pdo->prepare("SELECT id FROM clients WHERE name = 'Faizan Khan' LIMIT 1");
        $chkFaizan->execute();
        $faizanId = $chkFaizan->fetchColumn();
        if (!$faizanId) {
            $pdo->prepare("INSERT INTO clients(name, timezone, is_active, created_at) VALUES('Faizan Khan', 'Asia/Kolkata', 1, ?)")
                ->execute([date('Y-m-d H:i:s')]);
            $faizanId = (int)$pdo->lastInsertId();
        }

        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM accounts WHERE client_id = " . (int)$faizanId)->fetchColumn();
        if ($cnt === 0) {
            $pages = [
                // 5 Facebook Pages
                ['facebook', 'Faizan Official Brand', '109283741001', 'page', 'Facebook Page', 'mock_fb_token_1'],
                ['facebook', 'Faizan Tech & Code', '109283741002', 'page', 'Technology & Software', 'mock_fb_token_2'],
                ['facebook', 'Faizan Digital Agency', '109283741003', 'page', 'Digital Marketing & Ads', 'mock_fb_token_3'],
                ['facebook', 'Faizan E-Commerce Hub', '109283741004', 'page', 'Retail & Shopping', 'mock_fb_token_4'],
                ['facebook', 'Faizan Community Portal', '109283741005', 'page', 'Community & Media', 'mock_fb_token_5'],
                // 5 Instagram Accounts
                ['instagram', '@faizan_official', '17841401001', 'instagram', 'Creator Profile', 'mock_ig_token_1'],
                ['instagram', '@faizan_tech', '17841401002', 'instagram', 'Tech & Tutorials', 'mock_ig_token_2'],
                ['instagram', '@faizan_agency', '17841401003', 'instagram', 'Marketing Agency', 'mock_ig_token_3'],
                ['instagram', '@faizan_store', '17841401004', 'instagram', 'E-Commerce Store', 'mock_ig_token_4'],
                ['instagram', '@faizan_community', '17841401005', 'instagram', 'Community & Culture', 'mock_ig_token_5'],
                // LinkedIn Profiles & Pages
                ['linkedin', 'Faizan Khan (Personal)', 'urn:li:person:faizan_k', 'profile', 'Member Profile', 'mock_li_token_1'],
                ['linkedin', 'Faizan Digital Agency (Company)', 'urn:li:organization:984712', 'organization', 'Company Page', 'mock_li_token_2'],
            ];

            $insAcct = $pdo->prepare("
                INSERT INTO accounts(client_id, platform, label, external_id, connection_status, access_token, meta_page_id, meta_ig_id, linkedin_urn, page_category, created_at)
                VALUES(?, ?, ?, ?, 'connected', ?, ?, ?, ?, ?, ?)
            ");

            foreach ($pages as $p) {
                $metaPageId = $p[0] === 'facebook' ? $p[2] : null;
                $metaIgId = $p[0] === 'instagram' ? $p[2] : null;
                $liUrn = $p[0] === 'linkedin' ? $p[2] : null;
                $insAcct->execute([
                    $faizanId,
                    $p[0],
                    $p[1],
                    $p[2],
                    $p[5],
                    $metaPageId,
                    $metaIgId,
                    $liUrn,
                    $p[4],
                    date('Y-m-d H:i:s')
                ]);
            }
        }

        $pdo->exec('PRAGMA user_version = 6;');
    }
    if ($version < 7) {
        // Version 7: LinkedIn 5 Accounts/Pages + Pinterest 5 Boards/Accounts for Faizan Khan
        $acctCols = $pdo->query("PRAGMA table_info(accounts)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('pinterest_board_id', $acctCols, true)) {
            $pdo->exec("ALTER TABLE accounts ADD COLUMN pinterest_board_id TEXT;");
        }

        $chkFaizan = $pdo->prepare("SELECT id FROM clients WHERE name = 'Faizan Khan' LIMIT 1");
        $chkFaizan->execute();
        $faizanId = (int)$chkFaizan->fetchColumn();

        if ($faizanId > 0) {
            $additionalAccounts = [
                // 3 more LinkedIn company pages (5 LinkedIn total)
                ['linkedin', 'Faizan Tech Solutions (Company)', 'urn:li:organization:984713', 'organization', 'Technology & Software', 'mock_li_token_3'],
                ['linkedin', 'Faizan E-Commerce Hub (Company)', 'urn:li:organization:984714', 'organization', 'Retail & Shopping', 'mock_li_token_4'],
                ['linkedin', 'Faizan Media Network (Company)', 'urn:li:organization:984715', 'organization', 'Media & Community', 'mock_li_token_5'],

                // 5 Pinterest Boards
                ['pinterest', 'Faizan Tech & AI Infographics (Board)', '81920391001', 'board', 'Tech & Infographics', 'mock_pin_token_1'],
                ['pinterest', 'Digital Marketing Showcase (Board)', '81920391002', 'board', 'Marketing & Ads', 'mock_pin_token_2'],
                ['pinterest', 'E-Commerce Products & Gear (Board)', '81920391003', 'board', 'Product Showcase', 'mock_pin_token_3'],
                ['pinterest', 'Web & Brand Design Inspiration (Board)', '81920391004', 'board', 'Design & UI/UX', 'mock_pin_token_4'],
                ['pinterest', 'Motivation & Creator Tips (Board)', '81920391005', 'board', 'Quotes & Lifestyle', 'mock_pin_token_5'],
            ];

            $insAcct = $pdo->prepare("
                INSERT INTO accounts(client_id, platform, label, external_id, connection_status, access_token, meta_page_id, meta_ig_id, linkedin_urn, pinterest_board_id, page_category, created_at)
                VALUES(?, ?, ?, ?, 'connected', ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($additionalAccounts as $p) {
                $chkExists = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE client_id = ? AND platform = ? AND external_id = ?");
                $chkExists->execute([$faizanId, $p[0], $p[2]]);
                if ((int)$chkExists->fetchColumn() === 0) {
                    $liUrn = $p[0] === 'linkedin' ? $p[2] : null;
                    $pinBoardId = $p[0] === 'pinterest' ? $p[2] : null;
                    $insAcct->execute([
                        $faizanId,
                        $p[0],
                        $p[1],
                        $p[2],
                        $p[5],
                        null,
                        null,
                        $liUrn,
                        $pinBoardId,
                        $p[4],
                        date('Y-m-d H:i:s')
                    ]);
                }
            }
        }

        $pdo->exec('PRAGMA user_version = 7;');
    }
    if ($version < 8) {
        // Version 8: Jobs response_id, campaigns auto_boost and WSA Workspace consistency
        $jobCols = $pdo->query("PRAGMA table_info(jobs)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('response_id', $jobCols, true)) {
            $pdo->exec("ALTER TABLE jobs ADD COLUMN response_id TEXT;");
        }
        $cmpCols = $pdo->query("PRAGMA table_info(campaigns)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('auto_boost', $cmpCols, true)) {
            $pdo->exec("ALTER TABLE campaigns ADD COLUMN auto_boost INTEGER NOT NULL DEFAULT 0;");
        }
        if (!in_array('boost_comments', $cmpCols, true)) {
            $pdo->exec("ALTER TABLE campaigns ADD COLUMN boost_comments TEXT;");
        }
        // Normalize workspace name to WSA Digital Agency
        $pdo->exec("UPDATE clients SET name = 'WSA Digital Agency' WHERE id = 2;");
        $pdo->exec('PRAGMA user_version = 8;');
    }
    if ($version < 9) {
        // Version 9: Multi-Profile Facebook Group Distribution & Anti-Ban Drip Engine
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sender_profiles(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                profile_url TEXT,
                fb_user_id TEXT,
                avatar_url TEXT,
                is_enabled INTEGER NOT NULL DEFAULT 1,
                daily_limit INTEGER NOT NULL DEFAULT 20,
                shares_today INTEGER NOT NULL DEFAULT 0,
                last_share_at TEXT,
                cooldown_until TEXT,
                status TEXT NOT NULL DEFAULT 'active',
                notes TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_sender_profiles_client ON sender_profiles(client_id);
            CREATE INDEX IF NOT EXISTS idx_sender_profiles_enabled ON sender_profiles(is_enabled, status);

            CREATE TABLE IF NOT EXISTS group_targets(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                group_url TEXT NOT NULL,
                group_id TEXT,
                niche_category TEXT NOT NULL DEFAULT 'General',
                member_count TEXT,
                is_enabled INTEGER NOT NULL DEFAULT 1,
                requires_admin_approval INTEGER NOT NULL DEFAULT 0,
                last_shared_at TEXT,
                total_shares INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_group_targets_client ON group_targets(client_id);
            CREATE INDEX IF NOT EXISTS idx_group_targets_enabled ON group_targets(is_enabled);
            CREATE INDEX IF NOT EXISTS idx_group_targets_niche ON group_targets(niche_category);

            CREATE TABLE IF NOT EXISTS group_share_queue(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                campaign_id INTEGER,
                group_id INTEGER NOT NULL,
                sender_profile_id INTEGER,
                post_url TEXT NOT NULL,
                caption TEXT,
                status TEXT NOT NULL DEFAULT 'queued',
                scheduled_for TEXT NOT NULL,
                shared_at TEXT,
                error_message TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY(campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
                FOREIGN KEY(group_id) REFERENCES group_targets(id) ON DELETE CASCADE,
                FOREIGN KEY(sender_profile_id) REFERENCES sender_profiles(id) ON DELETE SET NULL
            );
            CREATE INDEX IF NOT EXISTS idx_group_share_queue_status ON group_share_queue(status, scheduled_for);
            CREATE INDEX IF NOT EXISTS idx_group_share_queue_client ON group_share_queue(client_id);
        ");

        // Seed initial sample sender profiles and targeted groups for Client 2 (WSA Digital Agency)
        $chkSenders = (int)$pdo->query("SELECT COUNT(*) FROM sender_profiles WHERE client_id = 2")->fetchColumn();
        if ($chkSenders === 0) {
            $insProfile = $pdo->prepare("
                INSERT INTO sender_profiles(client_id, name, profile_url, is_enabled, daily_limit, shares_today, status, notes, created_at)
                VALUES(2, ?, ?, 1, ?, 0, 'active', ?, datetime('now'))
            ");
            $insProfile->execute(['Profile 1 (Primary Admin)', 'https://facebook.com/wsa.agency1', 20, 'High trust account - 5 years old']);
            $insProfile->execute(['Profile 2 (Growth Marketer)', 'https://facebook.com/wsa.growth2', 20, 'Secondary warm account']);
            $insProfile->execute(['Profile 3 (Outreach Lead)', 'https://facebook.com/wsa.outreach3', 20, 'Warm account with 50+ groups joined']);
            $insProfile->execute(['Profile 4 (Brand Ambassador)', 'https://facebook.com/wsa.brand4', 20, 'Active daily sharer']);
            $insProfile->execute(['Profile 5 (Community Manager)', 'https://facebook.com/wsa.community5', 20, 'Local niche sharer']);
        }

        $chkGroups = (int)$pdo->query("SELECT COUNT(*) FROM group_targets WHERE client_id = 2")->fetchColumn();
        if ($chkGroups === 0) {
            $insGroup = $pdo->prepare("
                INSERT INTO group_targets(client_id, name, group_url, niche_category, member_count, is_enabled, created_at)
                VALUES(2, ?, ?, ?, ?, 1, datetime('now'))
            ");
            $insGroup->execute(['Digital Marketing & SEO Experts India', 'https://www.facebook.com/groups/digitalmarketingindia', 'Digital Marketing', '48K members']);
            $insGroup->execute(['USA Business & Startup Networking', 'https://www.facebook.com/groups/usabusinessnetworking', 'Business & Startups', '62K members']);
            $insGroup->execute(['E-Commerce & Shopify Brand Growth', 'https://www.facebook.com/groups/ecommercegrowthglobal', 'E-Commerce', '35K members']);
            $insGroup->execute(['Home Decor, Rugs & Interior Designers', 'https://www.facebook.com/groups/homedecorinteriors', 'Home & Decor', '29K members']);
            $insGroup->execute(['Social Media Marketing Strategies 2026', 'https://www.facebook.com/groups/socialmediamarketing2026', 'Social Media', '75K members']);
        }

        $pdo->exec('PRAGMA user_version = 9;');
    }
    if ($version < 10) {
        $qCols = $pdo->query("PRAGMA table_info(group_share_queue)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('shared_post_url', $qCols, true)) {
            $pdo->exec("ALTER TABLE group_share_queue ADD COLUMN shared_post_url TEXT;");
        }
        $pdo->exec('PRAGMA user_version = 10;');
    }
    if ($version < 11) {
        // Version 11: Associate Facebook Accounts directly with Groups (Profile-wise Groups Hub)
        $gCols = $pdo->query("PRAGMA table_info(group_targets)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('account_id', $gCols, true)) {
            $pdo->exec("ALTER TABLE group_targets ADD COLUMN account_id INTEGER;");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_group_targets_account ON group_targets(account_id);");
        }
        if (!in_array('sender_profile_id', $gCols, true)) {
            $pdo->exec("ALTER TABLE group_targets ADD COLUMN sender_profile_id INTEGER;");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_group_targets_sender ON group_targets(sender_profile_id);");
        }
        $pdo->exec('PRAGMA user_version = 11;');
    }
    if ($version < 12) {
        $qCols = $pdo->query("PRAGMA table_info(group_share_queue)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('sender_account_id', $qCols, true)) {
            $pdo->exec("ALTER TABLE group_share_queue ADD COLUMN sender_account_id INTEGER;");
        }
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_group_share_queue_sender_acc ON group_share_queue(sender_account_id);");
        $pdo->exec('PRAGMA user_version = 12;');
    }
    return $pdo;
}

function bootstrap_db(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS admins(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS clients(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        timezone TEXT NOT NULL DEFAULT 'Asia/Kolkata',
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS accounts(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER NOT NULL,
        platform TEXT NOT NULL,
        label TEXT NOT NULL,
        external_id TEXT,
        connection_status TEXT NOT NULL DEFAULT 'not_connected',
        daily_base_limit INTEGER NOT NULL DEFAULT 5,
        daily_max_limit INTEGER NOT NULL DEFAULT 15,
        health_status TEXT NOT NULL DEFAULT 'healthy',
        created_at TEXT NOT NULL,
        FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS destinations(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER NOT NULL,
        account_id INTEGER,
        name TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'page',
        url TEXT,
        posting_mode TEXT NOT NULL DEFAULT 'manual',
        created_at TEXT NOT NULL,
        FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE,
        FOREIGN KEY(account_id) REFERENCES accounts(id) ON DELETE SET NULL
    );
    CREATE TABLE IF NOT EXISTS campaigns(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        message TEXT,
        link_url TEXT,
        media_path TEXT,
        platforms TEXT,
        scheduled_for TEXT NOT NULL,
        approval_status TEXT NOT NULL DEFAULT 'approved',
        status TEXT NOT NULL DEFAULT 'scheduled',
        created_at TEXT NOT NULL,
        FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS jobs(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER NOT NULL,
        client_id INTEGER NOT NULL,
        platform TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'queued',
        execution_mode TEXT NOT NULL DEFAULT 'manual',
        scheduled_for TEXT NOT NULL,
        error_message TEXT,
        created_at TEXT NOT NULL,
        FOREIGN KEY(campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
        FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS activity(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER,
        message TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'info',
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS login_attempts(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT NOT NULL,
        attempted_at INTEGER NOT NULL
    );
    -- Performance & relational integrity indexes
    CREATE INDEX IF NOT EXISTS idx_accounts_client_id ON accounts(client_id);
    CREATE INDEX IF NOT EXISTS idx_campaigns_client_id ON campaigns(client_id);
    CREATE INDEX IF NOT EXISTS idx_campaigns_status ON campaigns(status);
    CREATE INDEX IF NOT EXISTS idx_jobs_campaign_id ON jobs(campaign_id);
    CREATE INDEX IF NOT EXISTS idx_jobs_client_id ON jobs(client_id);
    CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status);
    CREATE INDEX IF NOT EXISTS idx_jobs_status_sched ON jobs(status, scheduled_for);
    CREATE INDEX IF NOT EXISTS idx_activity_client_id ON activity(client_id);
    CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address, attempted_at);
    ");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($count === 0) {
        $st = $pdo->prepare("INSERT INTO admins(name,email,password_hash,created_at) VALUES(?,?,?,?)");
        $st->execute(['Administrator', 'admin@wsa.com', password_hash('admin123', PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
    }
    $cc = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    if ($cc === 0) {
        $pdo->prepare("INSERT INTO clients(name,timezone,created_at) VALUES(?,?,?)")->execute(['Demo Client', 'Asia/Kolkata', date('Y-m-d H:i:s')]);
    }
}
