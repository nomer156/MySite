const sqlite3 = require('sqlite3').verbose();
const path = require('path');
const winston = require('winston');

// Configure logger
const logger = winston.createLogger({
  level: 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.json()
  ),
  transports: [
    new winston.transports.File({ filename: 'error.log', level: 'error' }),
    new winston.transports.File({ filename: 'combined.log' }),
    new winston.transports.Console({
      format: winston.format.simple()
    })
  ]
});

// Initialize database
const db = new sqlite3.Database(path.join(__dirname, 'vexio.db'), (err) => {
  if (err) {
    logger.error('Error connecting to database:', err);
    return;
  }
  logger.info('Connected to SQLite database');
  initDatabase();
});

// Create tables if they don't exist
const initDatabase = () => {
  db.serialize(() => {
    db.run(`
      CREATE TABLE IF NOT EXISTS bot_skins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        asset_id TEXT NOT NULL,
        market_hash_name TEXT NOT NULL,
        price_tp INTEGER NOT NULL,
        wear_value REAL,
        exterior TEXT,
        image_url TEXT,
        is_available INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )
    `, (err) => {
      if (err) logger.error('Error creating bot_skins table:', err);
    });

    db.run(`
      CREATE TABLE IF NOT EXISTS skin_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_steam_id TEXT NOT NULL,
        asset_id TEXT NOT NULL,
        price_tp INTEGER NOT NULL,
        trade_offer_id TEXT,
        status TEXT CHECK(status IN ('pending', 'completed', 'failed')) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )
    `, (err) => {
      if (err) logger.error('Error creating skin_transactions table:', err);
    });

    db.run(`CREATE INDEX IF NOT EXISTS idx_skins_available ON bot_skins(is_available)`, (err) => {
      if (err) logger.error('Error creating skins index:', err);
    });

    db.run(`CREATE INDEX IF NOT EXISTS idx_transactions_status ON skin_transactions(status)`, (err) => {
      if (err) logger.error('Error creating transactions index:', err);
    });
  });
};

// Helper functions for database operations
const dbWrapper = {
  // Get all available skins
  getAvailableSkins() {
    return new Promise((resolve, reject) => {
      db.all('SELECT * FROM bot_skins WHERE is_available = 1', (err, rows) => {
        if (err) reject(err);
        else resolve(rows);
      });
    });
  },

  // Get a specific skin by ID
  getSkinById(id) {
    return new Promise((resolve, reject) => {
      db.get('SELECT * FROM bot_skins WHERE id = ?', [id], (err, row) => {
        if (err) reject(err);
        else resolve(row);
      });
    });
  },

  // Create a new transaction
  createTransaction(userSteamId, assetId, priceTP, tradeOfferId) {
    return new Promise((resolve, reject) => {
      db.run(`
        INSERT INTO skin_transactions 
        (user_steam_id, asset_id, price_tp, trade_offer_id) 
        VALUES (?, ?, ?, ?)
      `, [userSteamId, assetId, priceTP, tradeOfferId], function(err) {
        if (err) reject(err);
        else resolve({ id: this.lastID });
      });
    });
  },

  // Update transaction status
  updateTransactionStatus(tradeOfferId, status) {
    return new Promise((resolve, reject) => {
      db.run(`
        UPDATE skin_transactions 
        SET status = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE trade_offer_id = ?
      `, [status, tradeOfferId], function(err) {
        if (err) reject(err);
        else resolve({ changes: this.changes });
      });
    });
  },

  // Update skin availability
  updateSkinAvailability(skinId, isAvailable) {
    return new Promise((resolve, reject) => {
      db.run('UPDATE bot_skins SET is_available = ? WHERE id = ?', 
        [isAvailable ? 1 : 0, skinId], function(err) {
        if (err) reject(err);
        else resolve({ changes: this.changes });
      });
    });
  }
};

module.exports = dbWrapper;