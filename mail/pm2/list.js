import { execSync } from 'child_process';

try {
  const output = execSync('pm2 list', { encoding: 'utf-8' });
  console.log(output);
} catch (error) {
  console.error(error.message);
}
