const { spawn } = require('child_process');
const readline = require('readline');
const path = require('path');

// ANSI Color Codes
const colors = {
  reset: "\x1b[0m",
  bright: "\x1b[1m",
  green: "\x1b[32m",
  yellow: "\x1b[33m",
  blue: "\x1b[34m",
  magenta: "\x1b[35m",
  cyan: "\x1b[36m",
  white: "\x1b[37m",
  bgBlue: "\x1b[44m",
};

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

const activeProcesses = [];

function startProcess(name, command, args, color, cwd = process.cwd()) {
  console.log(`${colors.bright}${color}  ${name.toUpperCase()}  ${colors.reset} Starting...`);
  const proc = spawn(command, args, {
    stdio: ['inherit', 'pipe', 'inherit'],
    shell: true,
    cwd
  });

  proc.stdout.on('data', (data) => {
    const lines = data.toString().split('\n');
    lines.forEach(line => {
      if (line.trim()) {
        process.stdout.write(`${color}[${name}]${colors.reset} ${line}\n`);
      }
    });
  });

  activeProcesses.push(proc);
  return proc;
}

async function main() {
  console.clear();
  console.log(`\n${colors.bright}${colors.cyan}TDT-IMS DEVELOPMENT ENVIRONMENT${colors.reset}\n`);
  
  console.log(`${colors.bright}SELECT MODE:${colors.reset}`);
  console.log(`1. Local Only [Port 8001]`);
  console.log(`2. Local + ngrok Tunnel`);
  console.log(`3. Exit\n`);

  rl.question(`${colors.yellow}Enter your choice (1-3): ${colors.reset}`, (choice) => {
    const rootDir = path.resolve(__dirname, '..');
    
    const runPhp = () => startProcess('ims', 'php', ['-S', '0.0.0.0:8001'], colors.bgBlue, rootDir);
    const runNgrok = () => startProcess('tunnel', 'ngrok', ['http', '8001'], colors.magenta, rootDir);

    switch(choice) {
      case '1':
        runPhp();
        break;
      case '2':
        runPhp();
        runNgrok();
        break;
      case '3':
        process.exit();
        break;
      default:
        console.log(`${colors.red}Invalid choice.${colors.reset}`);
        process.exit();
    }
  });
}

process.on('SIGINT', () => {
  console.log(`\n\n${colors.bright}${colors.yellow}Shutting down IMS...${colors.reset}`);
  activeProcesses.forEach(p => p.kill());
  process.exit();
});

main();
