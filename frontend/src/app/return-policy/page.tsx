import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: '退換貨政策',
  description: '婕樂纖仙女館退換貨政策，了解退換貨條件、流程及退款方式。',
  alternates: { canonical: '/return-policy' },
};

export default function ReturnPolicyPage() {
  return (
    <div className="max-w-[800px] mx-auto px-5 sm:px-6 lg:px-8 py-12 sm:py-16">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">退換貨政策</h1>

      <div className="prose-article text-gray-700 space-y-8 text-[15px] leading-relaxed">
        <p>
          感謝您選購「婕樂纖仙女館」的商品。為保障您的消費權益，請詳閱以下退換貨政策。
        </p>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">一、退換貨條件</h2>
          <p>
            依據消費者保護法規定，您享有商品到貨後 <strong>七日鑑賞期</strong>（非試用期）之權利。在鑑賞期內，如欲辦理退換貨，商品須符合以下條件：
          </p>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li>商品未經拆封、使用，且保持原始包裝完整</li>
            <li>商品外包裝、附件、贈品等均完整無缺</li>
            <li>商品未受人為損壞</li>
            <li>附上原始出貨單或訂單編號</li>
          </ul>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">二、不適用退換貨之情形</h2>
          <p>以下情形恕不接受退換貨：</p>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li>商品已拆封或使用（含保健食品已拆封食用）</li>
            <li>商品因個人因素造成損壞或污損</li>
            <li>超過七日鑑賞期</li>
            <li>非本網站購買之商品</li>
            <li>特殊促銷活動商品（將於商品頁面另行標註）</li>
          </ul>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">三、退換貨流程</h2>
          <ol className="list-decimal pl-5 space-y-2 mt-2">
            <li>
              <strong>提出申請：</strong>請於收到商品後七日內，透過客服信箱
              <a href="mailto:contact@freeco.cc" className="text-[#9F6B3E] hover:underline mx-1">
                contact@freeco.cc
              </a>
              或 Instagram 私訊聯繫我們，告知訂單編號及退換貨原因。
            </li>
            <li>
              <strong>確認申請：</strong>我們將於收到申請後 1-2 個工作日內回覆，確認退換貨事宜並提供寄回資訊。
            </li>
            <li>
              <strong>寄回商品：</strong>請將商品妥善包裝後寄回指定地址，退貨運費依個案處理（商品瑕疵由我方負擔，個人因素由消費者負擔）。
            </li>
            <li>
              <strong>商品檢查：</strong>我們收到退回商品後，將於 3-5 個工作日內完成檢查。
            </li>
            <li>
              <strong>完成退換貨：</strong>檢查通過後，將進行退款或寄出更換商品。
            </li>
          </ol>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">四、退款方式</h2>
          <ul className="list-disc pl-5 space-y-1 mt-2">
            <li><strong>信用卡付款：</strong>退款將退回原信用卡帳戶，預計 7-14 個工作日到帳（依各銀行作業時間而定）。</li>
            <li><strong>超商取貨付款：</strong>退款將以銀行匯款方式退回，請提供您的銀行帳戶資訊。</li>
            <li><strong>ATM 轉帳：</strong>退款將匯入您的指定帳戶，預計 3-5 個工作日到帳。</li>
          </ul>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">五、商品瑕疵處理</h2>
          <p>
            若您收到的商品有瑕疵或與訂購內容不符，請於收貨後七日內聯繫我們，我們將優先為您處理換貨或退款，相關運費由我方負擔。
          </p>
        </section>

        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">六、聯絡方式</h2>
          <p>如有任何退換貨相關問題，歡迎透過以下方式與我們聯繫：</p>
          <ul className="list-none space-y-1 mt-2">
            <li>
              客服信箱：
              <a href="mailto:contact@freeco.cc" className="text-[#9F6B3E] hover:underline">
                contact@freeco.cc
              </a>
            </li>
            <li>
              Instagram：
              <a
                href="https://www.instagram.com/pandorasdo/"
                target="_blank"
                rel="noopener noreferrer"
                className="text-[#9F6B3E] hover:underline"
              >
                @pandorasdo
              </a>
            </li>
          </ul>
        </section>

        <p className="text-sm text-gray-500 mt-8">最後更新日期：2026 年 4 月 12 日</p>
      </div>
    </div>
  );
}
