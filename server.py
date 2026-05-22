from flask import Flask, render_template, jsonify, request
import subprocess
import sys
import os

app = Flask(__name__)

# Initial detected object
detected_object = "None"

# Map exercise slugs to Python scripts
EXERCISE_SCRIPTS = {
    'lateral-rise': 'exercise/lateral2.py',
    'alt-dumbbell-curls': 'exercise/alternative2.py',
    'barbell-row': 'exercise/barbell2.py',
    'push-up': 'exercise/Pushup.py',
    'squats': 'exercise/squat.py',
    'shoulder-press': 'exercise/Shoulder_press.py',
    'tricep-dips': 'exercise/Tricep_Dips.py',
}

@app.route('/')
def index():
    return render_template('Exercise.html')

@app.route('/guide')
def guide():
    return render_template('guide.html')

@app.route('/run-exercise/<exercise>')
def run_exercise(exercise):
    script = EXERCISE_SCRIPTS.get(exercise)
    if not script:
        return jsonify(error=f"Unknown exercise: {exercise}"), 404

    script_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), script)
    if not os.path.exists(script_path):
        return jsonify(error=f"Script not found: {script}"), 404

    try:
        # Launch the exercise script as a separate process (non-blocking)
        subprocess.Popen([sys.executable, script_path], cwd=os.path.dirname(os.path.abspath(__file__)))
        return jsonify(output=f"Started {exercise} tracking!")
    except Exception as e:
        return jsonify(error=str(e)), 500

@app.route('/update', methods=['POST'])
def update():
    global detected_object
    detected_object = request.json.get('object_name', 'None')
    return jsonify(success=True)

@app.route('/get_object')
def get_object():
    return jsonify(object_name=detected_object)

if __name__ == '__main__':
    app.run(debug=True, port=5000)
