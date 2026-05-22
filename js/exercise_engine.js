// ====================================================================
// EXERCISE ENGINE - Strict Validation, Phase Detection, Mismatch Detection
// ====================================================================

// ---- Utility Functions ----
function calcAngle(a, b, c) {
    const rad = Math.atan2(c.y - b.y, c.x - b.x) - Math.atan2(a.y - b.y, a.x - b.x);
    let angle = Math.abs(rad * 180.0 / Math.PI);
    if (angle > 180) angle = 360 - angle;
    return angle;
}
function interp(v, iMin, iMax, oMin, oMax) { return oMin + (v - iMin) * (oMax - oMin) / (iMax - iMin); }
function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

// ---- Body Orientation Classifier ----
function classifyOrientation(lm) {
    const lShoulder = lm[11], rShoulder = lm[12];
    const lHip = lm[23], rHip = lm[24];
    const lAnkle = lm[27], rAnkle = lm[28];
    const shoulderMidY = (lShoulder.y + rShoulder.y) / 2;
    const hipMidY = (lHip.y + rHip.y) / 2;
    const ankleMidY = (lAnkle.y + rAnkle.y) / 2;
    // Prone/supine: shoulders and hips at similar Y, body mostly horizontal
    const verticalSpan = Math.abs(shoulderMidY - ankleMidY);
    const horizSpan = Math.abs((lShoulder.x + rShoulder.x) / 2 - (lAnkle.x + rAnkle.x) / 2);
    if (horizSpan > verticalSpan * 1.2) return 'horizontal'; // push-up/plank position
    if (shoulderMidY < hipMidY) return 'upright'; // standing
    return 'inverted';
}

// ---- Motion Signature Classifier ----
// Each exercise has a unique signature of which joints move and in what pattern
function computeMotionSignature(lm) {
    return {
        lElbowAngle: calcAngle(lm[11], lm[13], lm[15]),
        rElbowAngle: calcAngle(lm[12], lm[14], lm[16]),
        lKneeAngle: calcAngle(lm[23], lm[25], lm[27]),
        rKneeAngle: calcAngle(lm[24], lm[26], lm[28]),
        lShoulderAngle: calcAngle(lm[23], lm[11], lm[13]),
        rShoulderAngle: calcAngle(lm[24], lm[12], lm[14]),
        hipAngle: calcAngle(lm[11], lm[23], lm[25]),
        torsoAngle: calcAngle(lm[11], lm[23], lm[25]),
        orientation: classifyOrientation(lm),
        // Arm-above-shoulder check
        lWristAboveShoulder: lm[15].y < lm[11].y,
        rWristAboveShoulder: lm[16].y < lm[12].y,
        // Leg spread
        ankleSpread: Math.abs(lm[27].x - lm[28].x),
        hipWidth: Math.abs(lm[23].x - lm[24].x),
        // Elbow pinned
        lElbowDrift: Math.abs(lm[13].x - lm[11].x),
        rElbowDrift: Math.abs(lm[14].x - lm[12].x),
    };
}

// ---- Exercise Mismatch Validator ----
// Returns { matched: bool, reason: string }
const EXERCISE_VALIDATORS = {
    'squats': (sig) => {
        if (sig.orientation !== 'upright') return { matched: false, reason: 'Stand upright for squats' };
        // Knees should be moving (not arms)
        const kneeMoving = sig.lKneeAngle < 155 || sig.rKneeAngle < 155 || (sig.lKneeAngle > 150 && sig.rKneeAngle > 150);
        // Arms should NOT be curling
        const armsRelaxed = sig.lElbowAngle > 120 && sig.rElbowAngle > 120;
        if (!armsRelaxed && sig.lElbowAngle < 80) return { matched: false, reason: 'Incorrect exercise — arms should be relaxed for squats' };
        return { matched: true, reason: '' };
    },
    'push-up': (sig) => {
        if (sig.orientation === 'upright') return { matched: false, reason: 'Get into push-up position (horizontal)' };
        return { matched: true, reason: '' };
    },
    'dumbbell-curls': (sig) => {
        if (sig.orientation !== 'upright') return { matched: false, reason: 'Stand upright for curls' };
        // Knees should be mostly straight
        if (sig.lKneeAngle < 120 && sig.rKneeAngle < 120) return { matched: false, reason: 'Incorrect exercise — keep legs straight for curls' };
        return { matched: true, reason: '' };
    },
    'alt-dumbbell-curls': (sig) => {
        if (sig.orientation !== 'upright') return { matched: false, reason: 'Stand upright for curls' };
        if (sig.lKneeAngle < 120 && sig.rKneeAngle < 120) return { matched: false, reason: 'Incorrect exercise — keep legs straight for curls' };
        return { matched: true, reason: '' };
    },
    'lunges': (sig) => {
        if (sig.orientation !== 'upright') return { matched: false, reason: 'Stand upright for lunges' };
        return { matched: true, reason: '' };
    },
    'shoulder-press': (sig) => {
        if (sig.orientation !== 'upright') return { matched: false, reason: 'Stand upright for shoulder press' };
        // Elbows should be at or above shoulder level during motion
        if (sig.lKneeAngle < 120 && sig.rKneeAngle < 120) return { matched: false, reason: 'Incorrect exercise — keep legs straight' };
        return { matched: true, reason: '' };
    },
    'jumping-jacks': (sig) => {
        if (sig.orientation !== 'upright') return { matched: false, reason: 'Stand upright for jumping jacks' };
        return { matched: true, reason: '' };
    },
    'lateral-rise': (sig) => {
        if (sig.orientation !== 'upright') return { matched: false, reason: 'Stand upright for lateral raise' };
        if (sig.lKneeAngle < 120 && sig.rKneeAngle < 120) return { matched: false, reason: 'Incorrect exercise — keep legs straight' };
        return { matched: true, reason: '' };
    },
    'barbell-row': (sig) => {
        if (sig.orientation === 'horizontal') return { matched: false, reason: 'Stand and bend forward for barbell row' };
        return { matched: true, reason: '' };
    },
    'tricep-dips': (sig) => {
        if (sig.orientation === 'horizontal') return { matched: false, reason: 'Sit on edge for tricep dips' };
        return { matched: true, reason: '' };
    }
};

// ====================================================================
// EXERCISE DEFINITIONS - Enhanced with strict posture & phase detection
// ====================================================================
const EXERCISES = {
    'squats': {
        name: 'Squats', icon: '🦵',
        gif: 'static/gifss/squats.png',
        desc: 'Stand with feet shoulder-width apart. Lower your body by bending your knees, keeping your back straight.',
        phaseLabels: { down: '⬇ Going Down', up: '⬆ Standing Up', hold: '⏸ Hold' },
        downThreshold: 20, upThreshold: 80,
        detect(lm) {
            const lA = calcAngle(lm[23], lm[25], lm[27]);
            const rA = calcAngle(lm[24], lm[26], lm[28]);
            const avg = (lA + rA) / 2;
            const per = clamp(interp(avg, 70, 160, 0, 100), 0, 100);
            const backL = calcAngle(lm[11], lm[23], lm[25]);
            const backR = calcAngle(lm[12], lm[24], lm[26]);
            const backOk = backL > 135 && backR > 135;
            const kneeOk = Math.abs(lm[25].x - lm[27].x) < 0.12 && Math.abs(lm[26].x - lm[28].x) < 0.12;
            const symOk = Math.abs(lA - rA) < 25;
            const good = backOk && kneeOk && symOk;
            let msg = '', badJ = [];
            if (!backOk) { msg = 'Keep back straight'; badJ.push(11, 12, 23, 24); }
            else if (!kneeOk) { msg = 'Keep knees over ankles'; badJ.push(25, 26, 27, 28); }
            else if (!symOk) { msg = 'Keep legs symmetrical'; badJ.push(25, 26); }
            return { angle: avg, percentage: per, goodPosture: good, badJoints: badJ, postureMsg: msg };
        }
    },
    'push-up': {
        name: 'Push-Ups', icon: '🤸',
        gif: 'static/gifss/push_up.png',
        desc: 'Keep your body straight as you lower yourself down. Push back up to the starting position.',
        phaseLabels: { down: '⬇ Lowering', up: '⬆ Pushing Up', hold: '⏸ Hold' },
        downThreshold: 20, upThreshold: 80,
        detect(lm) {
            const lA = calcAngle(lm[11], lm[13], lm[15]);
            const rA = calcAngle(lm[12], lm[14], lm[16]);
            const avg = (lA + rA) / 2;
            const per = clamp(interp(avg, 70, 160, 0, 100), 0, 100);
            const bodyL = calcAngle(lm[11], lm[23], lm[27]);
            const bodyR = calcAngle(lm[12], lm[24], lm[28]);
            const bodyOk = bodyL > 145 && bodyR > 145;
            const symOk = Math.abs(lA - rA) < 25;
            const good = bodyOk && symOk;
            let msg = '', badJ = [];
            if (!bodyOk) { msg = 'Keep body in a straight line'; badJ.push(11, 12, 23, 24, 27, 28); }
            else if (!symOk) { msg = 'Keep arms symmetrical'; badJ.push(13, 14); }
            return { angle: avg, percentage: per, goodPosture: good, badJoints: badJ, postureMsg: msg };
        }
    },
    'dumbbell-curls': {
        name: 'Dumbbell Curls', icon: '💪',
        gif: 'static/gifss/dumbbell_curls.png',
        desc: 'Hold dumbbells at your sides, curl both arms simultaneously. Keep elbows close to your body.',
        phaseLabels: { down: '⬇ Lowering', up: '⬆ Curling Up', hold: '⏸ Hold' },
        downThreshold: 20, upThreshold: 80,
        detect(lm) {
            const lA = calcAngle(lm[11], lm[13], lm[15]);
            const rA = calcAngle(lm[12], lm[14], lm[16]);
            const avg = (lA + rA) / 2;
            const per = clamp(interp(avg, 40, 160, 0, 100), 0, 100);
            const eDL = Math.abs(lm[13].x - lm[11].x);
            const eDR = Math.abs(lm[14].x - lm[12].x);
            const elbowOk = eDL < 0.12 && eDR < 0.12;
            const symOk = Math.abs(lA - rA) < 30;
            const good = elbowOk && symOk;
            let msg = '', badJ = [];
            if (!elbowOk) { msg = 'Keep elbows close to body'; badJ.push(13, 14); }
            else if (!symOk) { msg = 'Curl both arms evenly'; badJ.push(13, 14, 15, 16); }
            return { angle: avg, percentage: per, goodPosture: good, badJoints: badJ, postureMsg: msg };
        }
    },
    'alt-dumbbell-curls': {
        name: 'Alternating Curls', icon: '💪',
        gif: 'static/gifss/alternative_dumbbell_curls.png',
        desc: 'Perform one arm curl at a time, alternating between left and right. Keep elbows pinned.',
        phaseLabels: { down: '⬇ Lowering', up: '⬆ Curling Up', hold: '⏸ Hold' },
        downThreshold: 20, upThreshold: 80, isAlternating: true,
        detect(lm) {
            const lA = calcAngle(lm[11], lm[13], lm[15]);
            const rA = calcAngle(lm[12], lm[14], lm[16]);
            this._lAngle = lA; this._rAngle = rA;
            const activeAngle = Math.min(lA, rA);
            const per = clamp(interp(activeAngle, 40, 160, 0, 100), 0, 100);
            const eDL = Math.abs(lm[13].x - lm[11].x);
            const eDR = Math.abs(lm[14].x - lm[12].x);
            const elbowOk = eDL < 0.12 && eDR < 0.12;
            let msg = '', badJ = [];
            if (!elbowOk) { msg = 'Keep elbows close to body'; badJ.push(13, 14); }
            return { angle: activeAngle, percentage: per, goodPosture: elbowOk, badJoints: badJ, postureMsg: msg };
        }
    },
    'lunges': {
        name: 'Lunges', icon: '🏃',
        gif: 'static/gifss/lunges.png',
        desc: 'Step forward with one leg, lowering hips until both knees are bent at about 90 degrees.',
        phaseLabels: { down: '⬇ Lunging Down', up: '⬆ Standing Up', hold: '⏸ Hold' },
        downThreshold: 20, upThreshold: 80,
        detect(lm) {
            const lK = calcAngle(lm[23], lm[25], lm[27]);
            const rK = calcAngle(lm[24], lm[26], lm[28]);
            const front = Math.min(lK, rK);
            const per = clamp(interp(front, 70, 165, 0, 100), 0, 100);
            const torso = calcAngle(lm[11], lm[23], lm[25]);
            const torsoOk = torso > 135;
            let msg = '', badJ = [];
            if (!torsoOk) { msg = 'Keep torso upright'; badJ.push(11, 12, 23, 24); }
            return { angle: front, percentage: per, goodPosture: torsoOk, badJoints: badJ, postureMsg: msg };
        }
    },
    'shoulder-press': {
        name: 'Shoulder Press', icon: '🙆‍♂️',
        gif: 'static/gifss/shoulder_press.png',
        desc: 'Press weights overhead from shoulder height, extending arms fully, then lower back down.',
        phaseLabels: { down: '⬇ Lowering', up: '⬆ Pressing Up', hold: '⏸ Hold' },
        downThreshold: 20, upThreshold: 80,
        detect(lm) {
            const lA = calcAngle(lm[11], lm[13], lm[15]);
            const rA = calcAngle(lm[12], lm[14], lm[16]);
            const avg = (lA + rA) / 2;
            const per = clamp(interp(avg, 80, 170, 0, 100), 0, 100);
            const symOk = Math.abs(lA - rA) < 30;
            const backOk = calcAngle(lm[12], lm[24], lm[26]) > 160;
            const good = symOk && backOk;
            let msg = '', badJ = [];
            if (!symOk) { msg = 'Keep arms symmetrical'; badJ.push(13, 14, 15, 16); }
            else if (!backOk) { msg = 'Keep back straight'; badJ.push(12, 24); }
            return { angle: avg, percentage: per, goodPosture: good, badJoints: badJ, postureMsg: msg };
        }
    },
    'jumping-jacks': {
        name: 'Jumping Jacks', icon: '⭐',
        gif: 'static/gifss/jumping_jacks.png',
        desc: 'Jump while spreading legs and clapping hands overhead, then jump back to standing position.',
        phaseLabels: { down: '🔽 Closed', up: '🔼 Open', hold: '⏸ Hold' },
        downThreshold: 25, upThreshold: 75, isJumpingJack: true,
        detect(lm) {
            const lArm = calcAngle(lm[23], lm[11], lm[15]);
            const rArm = calcAngle(lm[24], lm[12], lm[16]);
            const armA = (lArm + rArm) / 2;
            const hipW = Math.abs(lm[23].x - lm[24].x);
            const ankW = Math.abs(lm[27].x - lm[28].x);
            const legR = hipW > 0 ? ankW / hipW : 1;
            this._openScore = (armA > 120 && legR > 1.8) ? 100 : 0;
            this._closedScore = (armA < 60 && legR < 1.3) ? 100 : 0;
            const per = clamp(interp(armA, 40, 150, 0, 100), 0, 100);
            return { angle: armA, percentage: per, goodPosture: true, badJoints: [], postureMsg: '' };
        }
    },
    'lateral-rise': {
        name: 'Lateral Raise', icon: '🏋️',
        gif: 'static/gifss/lateral_raise.png',
        desc: 'Hold dumbbells at your sides and raise them out to the side until shoulder height.',
        phaseLabels: { down: '⬇ Lowering', up: '⬆ Raising', hold: '⏸ Hold' },
        downThreshold: 15, upThreshold: 85, invertPhase: true,
        detect(lm) {
            const lA = calcAngle(lm[23], lm[11], lm[13]);
            const rA = calcAngle(lm[24], lm[12], lm[14]);
            const avg = (lA + rA) / 2;
            const per = clamp(interp(avg, 15, 85, 0, 100), 0, 100);
            const symOk = Math.abs(lA - rA) < 25;
            let msg = '', badJ = [];
            if (!symOk) { msg = 'Raise both arms evenly'; badJ.push(11, 12, 13, 14); }
            return { angle: avg, percentage: per, goodPosture: symOk, badJoints: badJ, postureMsg: msg };
        }
    },
    'barbell-row': {
        name: 'Barbell Row', icon: '🏋️‍♂️',
        gif: 'static/gifss/barbell_row.png',
        desc: 'Bend over at the waist and pull the barbell towards your lower chest, keeping back flat.',
        phaseLabels: { down: '⬇ Lowering', up: '⬆ Pulling', hold: '⏸ Hold' },
        downThreshold: 20, upThreshold: 80,
        detect(lm) {
            const lA = calcAngle(lm[11], lm[13], lm[15]);
            const rA = calcAngle(lm[12], lm[14], lm[16]);
            const avg = (lA + rA) / 2;
            const per = clamp(interp(avg, 40, 160, 0, 100), 0, 100);
            const back = calcAngle(lm[11], lm[23], lm[25]);
            const backOk = back > 90 && back < 160;
            let msg = '', badJ = [];
            if (!backOk) { msg = 'Bend forward ~45°'; badJ.push(11, 12, 23, 24); }
            return { angle: avg, percentage: per, goodPosture: backOk, badJoints: badJ, postureMsg: msg };
        }
    },
    'tricep-dips': {
        name: 'Tricep Dips', icon: '💎',
        gif: 'static/gifss/tricep_dips.png',
        desc: 'Place hands on a bench behind you and lower your body by bending elbows. Push back up.',
        phaseLabels: { down: '⬇ Lowering', up: '⬆ Pushing Up', hold: '⏸ Hold' },
        downThreshold: 20, upThreshold: 80,
        detect(lm) {
            const lA = calcAngle(lm[11], lm[13], lm[15]);
            const rA = calcAngle(lm[12], lm[14], lm[16]);
            const avg = (lA + rA) / 2;
            const per = clamp(interp(avg, 60, 160, 0, 100), 0, 100);
            const symOk = Math.abs(lA - rA) < 25;
            let msg = '', badJ = [];
            if (!symOk) { msg = 'Keep arms symmetrical'; badJ.push(13, 14); }
            return { angle: avg, percentage: per, goodPosture: symOk, badJoints: badJ, postureMsg: msg };
        }
    }
};
