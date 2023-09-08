import { exec } from 'child_process';

const phpCommand = 'php think TronClear';
const workingDirectory = '../';

exec(phpCommand, { cwd: workingDirectory }, (error, stdout, stderr) => {
  if (error) {
    console.error(`Error executing PHP command: ${error}`);
    return;
  }
  console.log(`PHP command output: ${stdout}`);
});