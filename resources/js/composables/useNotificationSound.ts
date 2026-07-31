/**
 * 文件说明：前端组合式逻辑，封装后台消息提醒音的播放能力。
 */
import type { NotificationSound } from '@/types/generated';

type AudioContextConstructor = typeof AudioContext;

let audioContext: AudioContext | null = null;
let audioUnavailableWarningShown = false;

function warnAudioUnavailable(error?: unknown): void {
  if (audioUnavailableWarningShown) {
    return;
  }

  audioUnavailableWarningShown = true;
  console.warn('通知提示音不可用。', { error });
}

function resolveAudioContext(): AudioContext | null {
  if (typeof window === 'undefined') {
    return null;
  }

  const Context = (
    window as typeof window & {
      AudioContext?: AudioContextConstructor;
    }
  ).AudioContext;

  if (!Context) {
    warnAudioUnavailable();
    return null;
  }

  if (audioContext === null) {
    audioContext = new Context();
  }

  return audioContext;
}

function playTone(
  context: AudioContext,
  startAt: number,
  frequency: number,
  duration: number,
  volume: number,
  type: OscillatorType = 'sine',
): void {
  const oscillator = context.createOscillator();
  const gain = context.createGain();
  const endAt = startAt + duration;

  oscillator.type = type;
  oscillator.frequency.setValueAtTime(frequency, startAt);

  gain.gain.setValueAtTime(0.0001, startAt);
  gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.015);
  gain.gain.exponentialRampToValueAtTime(0.0001, endAt);

  oscillator.connect(gain);
  gain.connect(context.destination);
  oscillator.start(startAt);
  oscillator.stop(endAt);
}

function playPercussivePartial(
  context: AudioContext,
  startAt: number,
  frequency: number,
  duration: number,
  volume: number,
  type: OscillatorType = 'sine',
): void {
  const oscillator = context.createOscillator();
  const gain = context.createGain();
  const endAt = startAt + duration;

  oscillator.type = type;
  oscillator.frequency.setValueAtTime(frequency, startAt);

  gain.gain.setValueAtTime(0.0001, startAt);
  gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.004);
  gain.gain.exponentialRampToValueAtTime(volume * 0.28, startAt + 0.035);
  gain.gain.exponentialRampToValueAtTime(0.0001, endAt);

  oscillator.connect(gain);
  gain.connect(context.destination);
  oscillator.start(startAt);
  oscillator.stop(endAt);
}

function playNoteTone(context: AudioContext, startAt: number): void {
  playPercussivePartial(context, startAt, 659.25, 0.18, 0.078, 'triangle');
  playPercussivePartial(context, startAt + 0.003, 1318.51, 0.11, 0.014);
}

function playDingTone(context: AudioContext, startAt: number): void {
  playTone(context, startAt, 1174.66, 0.24, 0.075, 'sine');
  playTone(context, startAt, 2349.32, 0.17, 0.02, 'sine');
  playTone(context, startAt + 0.005, 3523, 0.1, 0.006, 'sine');
}

function playSweep(
  context: AudioContext,
  startAt: number,
  fromFrequency: number,
  toFrequency: number,
  duration: number,
  volume: number,
  type: OscillatorType = 'sine',
): void {
  const oscillator = context.createOscillator();
  const gain = context.createGain();
  const endAt = startAt + duration;

  oscillator.type = type;
  oscillator.frequency.setValueAtTime(fromFrequency, startAt);
  oscillator.frequency.exponentialRampToValueAtTime(toFrequency, endAt);

  gain.gain.setValueAtTime(0.0001, startAt);
  gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.01);
  gain.gain.exponentialRampToValueAtTime(0.0001, endAt);

  oscillator.connect(gain);
  gain.connect(context.destination);
  oscillator.start(startAt);
  oscillator.stop(endAt);
}

function playSoundPreset(
  context: AudioContext,
  sound: NotificationSound,
): void {
  const startAt = context.currentTime;

  if (sound === 'note') {
    playNoteTone(context, startAt);
    return;
  }

  if (sound === 'rebound') {
    playSweep(context, startAt, 830.61, 1244.51, 0.16, 0.06);
    playSweep(context, startAt + 0.085, 659.25, 987.77, 0.14, 0.04);
    return;
  }

  if (sound === 'pop') {
    playTone(context, startAt, 520, 0.08, 0.12, 'triangle');
    playTone(context, startAt + 0.045, 780, 0.1, 0.1, 'triangle');
    return;
  }

  if (sound === 'ding') {
    playDingTone(context, startAt);
    return;
  }
}

export function playNotificationSound(sound: NotificationSound = 'pop'): void {
  const context = resolveAudioContext();
  if (!context) {
    return;
  }

  void context
    .resume()
    .then(() => {
      playSoundPreset(context, sound);
    })
    .catch(warnAudioUnavailable);
}
