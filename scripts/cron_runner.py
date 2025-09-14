#!/usr/bin/env python3
"""
Cron Runner - Executes scheduled jobs from database
"""

import sys
import os
sys.path.append('/app')

import time
import sqlite3
from datetime import datetime

class CronRunner:
    def __init__(self):
        self.db_path = "/app/data/main.db"
        
    def run_due_jobs(self):
        """Execute jobs that are due to run"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            # Get due jobs
            cursor.execute("""
                SELECT * FROM cron_jobs 
                WHERE enabled = 1 AND next_run <= ?
            """, (now,))
            
            jobs = cursor.fetchall()
            
            for job in jobs:
                job_id, name, command, schedule, enabled, last_run, next_run, created_at = job
                
                print(f"Executing job: {name}")
                
                # Execute command
                os.system(f"{command} > /dev/null 2>&1 &")
                
                # Update job timing
                next_run_time = self.calculate_next_run(schedule)
                cursor.execute("""
                    UPDATE cron_jobs 
                    SET last_run = ?, next_run = ? 
                    WHERE id = ?
                """, (now, next_run_time, job_id))
            
            conn.commit()
            conn.close()
            
        except Exception as e:
            print(f"Cron runner error: {e}")
    
    def calculate_next_run(self, schedule):
        """Calculate next run time based on schedule"""
        from datetime import timedelta
        
        now = datetime.now()
        
        if schedule == '*/5 * * * *':  # Every 5 minutes
            return (now + timedelta(minutes=5)).strftime('%Y-%m-%d %H:%M:%S')
        elif schedule == '0 * * * *':  # Every hour
            return (now + timedelta(hours=1)).strftime('%Y-%m-%d %H:%M:%S')
        elif schedule == '0 0 * * *':  # Daily
            return (now + timedelta(days=1)).strftime('%Y-%m-%d %H:%M:%S')
        
        # Default: 1 hour
        return (now + timedelta(hours=1)).strftime('%Y-%m-%d %H:%M:%S')

if __name__ == "__main__":
    runner = CronRunner()
    
    while True:
        runner.run_due_jobs()
        time.sleep(60)  # Check every minute