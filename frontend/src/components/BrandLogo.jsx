function ChipGeometry() {
  return <>
    <g fill="none" stroke="#e10600" strokeWidth="2">
      <rect x="8" y="8" width="32" height="32" rx="4" />
      <rect x="15" y="15" width="18" height="18" rx="2" />
      <path d="M18 3v5m8-5v5m8-5v5M18 40v5m8-5v5m8-5v5M3 18h5m-5 8h5m-5 8h5m32-16h5m-5 8h5m-5 8h5" />
    </g>
    <path fill="#e10600" d="M20 20h8v8h-8z" />
  </>;
}

export function BrandMark({ className = "", title = "Elite PC" }) {
  return <svg className={`brand-mark ${className}`.trim()} viewBox="0 0 48 48" role="img" aria-label={title}>
    <ChipGeometry />
  </svg>;
}

export default function BrandLogo({ className = "", title = "Elite PC" }) {
  return (
    <svg className={`brand-logo ${className}`.trim()} viewBox="0 0 220 48" role="img" aria-label={title}>
      <ChipGeometry />
      <text x="57" y="33" fill="#f5f5f5" fontFamily="Rajdhani, Inter, Arial, sans-serif" fontSize="29" fontWeight="700" letterSpacing=".5">ELITE</text>
      <text x="137" y="33" fill="#e10600" fontFamily="Rajdhani, Inter, Arial, sans-serif" fontSize="29" fontWeight="700" letterSpacing=".5">PC</text>
    </svg>
  );
}
