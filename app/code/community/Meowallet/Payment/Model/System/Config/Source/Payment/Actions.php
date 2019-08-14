<?php
/**
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
 */

class Meowallet_Payment_Model_System_Config_Source_Payment_Actions
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Meowallet_Payment_Model_Meowallet_Abstract::ACTION_AUTHORIZE,
                'label' => Mage::helper('meowallet')->__('Authorize Only')
            ),
            array(
                'value' => Meowallet_Payment_Model_Meowallet_Abstract::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('meowallet')->__('Authorize and Capture')
            ),
        );
    }
}
