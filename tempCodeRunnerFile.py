
def run_exercise(action):
    script = EXERCISE_SCRIPTS.get(action)
    if script:
        try:
            # Log the action and script being executed
            print(f"Running script for action: {action}, Script: {script}")
            