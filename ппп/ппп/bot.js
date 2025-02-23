const SteamUser = require('steam-user');
const TradeOfferManager = require('steam-tradeoffer-manager');
const SteamCommunity = require('steamcommunity');
const express = require('express');
const bodyParser = require('body-parser');
const steamTotp = require('steam-totp');
const winston = require('winston');
require('dotenv').config();
const config = require('./config');
const db = require('./database');

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

// Initialize Steam client
const client = new SteamUser();
const manager = new TradeOfferManager({
  steam: client,
  domain: config.steam.domain,
  language: 'en',
  apiKey: config.steam.apiKey
});
const community = new SteamCommunity();

// Login to Steam
const logOnOptions = {
  accountName: config.steam.accountName,
  password: config.steam.password,
  twoFactorCode: steamTotp.generateAuthCode(config.steam.shared_secret)
};

client.logOn(logOnOptions);

client.on('loggedOn', () => {
  logger.info('Bot logged in successfully');
  client.setPersona(SteamUser.EPersonaState.Online);
});

client.on('error', (err) => {
  logger.error('Steam client error:', err);
});

client.on('webSession', (sessionID, cookies) => {
  manager.setCookies(cookies);
  community.setCookies(cookies);
  logger.info('Web session established');
});

// Express server setup
const app = express();
app.use(bodyParser.json());

// API Endpoints

// Get available skins
app.get('/api/skins', async (req, res) => {
  try {
    const [skins] = await db.query('SELECT * FROM bot_skins WHERE is_available = true');
    res.json(skins);
  } catch (error) {
    logger.error('Error fetching skins:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
});

// Purchase skin with TP
app.post('/api/purchase', async (req, res) => {
  const { userSteamId, skinId } = req.body;

  try {
    // Start transaction
    const connection = await db.getConnection();
    await connection.beginTransaction();

    try {
      // Get skin details and user TP balance
      const [[skin]] = await connection.query('SELECT * FROM bot_skins WHERE id = ? AND is_available = true', [skinId]);
      const [[user]] = await connection.query('SELECT tp_balance FROM users WHERE steam_id = ?', [userSteamId]);

      if (!skin || !user) {
        throw new Error('Skin not available or user not found');
      }

      if (user.tp_balance < skin.price_tp) {
        throw new Error('Insufficient TP balance');
      }

      // Create trade offer
      const offer = manager.createOffer(userSteamId);
      offer.addMyItem({
        assetid: skin.asset_id,
        appid: 730,
        contextid: 2
      });

      // Send trade offer
      const result = await new Promise((resolve, reject) => {
        offer.send((err, status) => {
          if (err) reject(err);
          else resolve({ status, offerId: offer.id });
        });
      });

      // Update database
      await connection.query('UPDATE users SET tp_balance = tp_balance - ? WHERE steam_id = ?', 
        [skin.price_tp, userSteamId]);
      
      await connection.query('INSERT INTO skin_transactions (user_steam_id, asset_id, price_tp, trade_offer_id) VALUES (?, ?, ?, ?)',
        [userSteamId, skin.asset_id, skin.price_tp, result.offerId]);

      await connection.query('UPDATE bot_skins SET is_available = false WHERE id = ?', [skinId]);

      await connection.commit();
      res.json({ success: true, tradeOfferId: result.offerId });

    } catch (error) {
      await connection.rollback();
      throw error;
    } finally {
      connection.release();
    }

  } catch (error) {
    logger.error('Error processing purchase:', error);
    res.status(500).json({ error: error.message });
  }
});

// Handle incoming trade offers
manager.on('newOffer', async (offer) => {
  try {
    // Only accept offers we initiated
    const [[transaction]] = await db.query(
      'SELECT * FROM skin_transactions WHERE trade_offer_id = ? AND status = "pending"',
      [offer.id]
    );

    if (!transaction) {
      await offer.decline();
      logger.info(`Declined unauthorized offer ${offer.id}`);
      return;
    }

    // Accept the offer
    await offer.accept();
    await db.query(
      'UPDATE skin_transactions SET status = "completed" WHERE trade_offer_id = ?',
      [offer.id]
    );
    logger.info(`Completed transaction ${transaction.id} for offer ${offer.id}`);

  } catch (error) {
    logger.error('Error handling trade offer:', error);
    try {
      await offer.decline();
      await db.query(
        'UPDATE skin_transactions SET status = "failed" WHERE trade_offer_id = ?',
        [offer.id]
      );
    } catch (declineError) {
      logger.error('Error declining offer:', declineError);
    }
  }
});

// Start server
app.listen(config.server.port, () => {
  logger.info(`Bot server running on port ${config.server.port}`);
});