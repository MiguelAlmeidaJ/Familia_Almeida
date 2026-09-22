import { randomBytes, scryptSync, timingSafeEqual } from 'node:crypto';

export function hashPassword(password) {
  const salt = randomBytes(16).toString('hex');
  const derivedKey = scryptSync(password, salt, 64).toString('hex');
  return `scrypt$${salt}$${derivedKey}`;
}

export function verifyPassword(password, stored) {
  try {
    const [algorithm, salt, key] = stored.split('$');
    if (algorithm !== 'scrypt' || !salt || !key) return false;
    const derivedKey = scryptSync(password, salt, 64);
    const storedKey = Buffer.from(key, 'hex');
    return storedKey.length === derivedKey.length && timingSafeEqual(storedKey, derivedKey);
  } catch {
    return false;
  }
}
