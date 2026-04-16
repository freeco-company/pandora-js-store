'use client';

/**
 * Decorative floating blobs — warm beige gradients that drift behind content.
 * Use inside a relatively-positioned section for background flair.
 */

export default function FloatingShapes() {
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
      <span className="shape s1" />
      <span className="shape s2" />
      <span className="shape s3" />
      <style jsx>{`
        .shape {
          position: absolute;
          border-radius: 999px;
          filter: blur(28px);
          opacity: 0.55;
          will-change: transform;
          contain: strict;
        }
        .s1 {
          top: -10%;
          left: -10%;
          width: 420px;
          height: 420px;
          background: radial-gradient(circle at 30% 30%, #e7d9cb, transparent 70%);
          animation: drift1 22s ease-in-out infinite alternate;
        }
        .s2 {
          top: 40%;
          right: -15%;
          width: 520px;
          height: 520px;
          background: radial-gradient(circle at 60% 40%, #d8b896, transparent 70%);
          animation: drift2 28s ease-in-out infinite alternate;
        }
        .s3 {
          bottom: -20%;
          left: 30%;
          width: 380px;
          height: 380px;
          background: radial-gradient(circle at 50% 50%, #f7c79a, transparent 70%);
          animation: drift3 25s ease-in-out infinite alternate;
          opacity: 0.35;
        }
        @keyframes drift1 {
          from { transform: translate(0, 0) scale(1); }
          to { transform: translate(80px, 60px) scale(1.15); }
        }
        @keyframes drift2 {
          from { transform: translate(0, 0) scale(1); }
          to { transform: translate(-100px, -40px) scale(0.9); }
        }
        @keyframes drift3 {
          from { transform: translate(0, 0) scale(1); }
          to { transform: translate(60px, -70px) scale(1.1); }
        }
        @media (prefers-reduced-motion: reduce), (hover: none) {
          /* Mobile — static blobs, no continuous drift animation */
          .shape { animation: none; }
        }
      `}</style>
    </div>
  );
}
