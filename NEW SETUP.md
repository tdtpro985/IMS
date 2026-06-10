# IMS Development Environment Setup

This document describes how to set up and run the Intern Management System (IMS) development environment using the simplified startup command.

## Running the Dev Environment

To start the development environment:
1. Open a terminal in the `INTERN-MANAGEMENT-SYSTEM` directory.
2. Run:
   ```bash
   npm run dev
   ```
3. You will be prompted with three options:
   * **1. Local Only [Port 8001]**: Spawns the local PHP development server on `http://localhost:8001`.
   * **2. Local + ngrok Tunnel**: Spawns the local PHP development server and creates a public ngrok tunnel pointing to port 8001 (`ngrok http 8001`).
   * **3. Exit**: Exits the script.

---

## Ngrok Setup for Teammates

If you push this code to GitHub and your teammates clone the repository, **yes, they must sign up for ngrok** if they want to run the public tunnel (Option 2). 

Here are the setup steps for teammates:
1. **Sign Up**: Sign up for a free account at [ngrok.com](https://ngrok.com/).
2. **Install ngrok**: Install the ngrok agent on their system.
3. **Configure Authtoken**: Copy their personal authtoken from the ngrok dashboard and run:
   ```bash
   ngrok config add-authtoken <their-personal-authtoken>
   ```
Once configured, they can also use Option 2 to generate public tunnels.

---

## Troubleshooting ngrok Tunnel Failures

If you select **Option 2** and the tunnel is not created, it is highly likely that your ngrok account has reached its simultaneous session limit (free accounts are limited to **3 simultaneous sessions**).

This happens when previous runs of the dev script do not clean up properly, leaving orphaned `ngrok` processes running in the background.

### How to Fix:
1. **Kill all active ngrok processes** on Windows by running the following command in Command Prompt or PowerShell:
   ```powershell
   taskkill /f /im ngrok.exe
   ```
2. Run `npm run dev` again and choose option `2`.
